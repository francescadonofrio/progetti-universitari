<?php
declare(strict_types=1);

function promo_load_dom(string $path): DOMDocument {
  if (!is_file($path)) {
    $doc = new DOMDocument("1.0", "UTF-8");
    $doc->preserveWhiteSpace = false;
    $doc->formatOutput = true;
    $root = $doc->createElement("promozioni");
    $doc->appendChild($root);
    $root->appendChild($doc->createElement("sconti"));
    $root->appendChild($doc->createElement("bonus"));
    $doc->save($path);
  }

  $doc = new DOMDocument();
  $doc->preserveWhiteSpace = false;
  $doc->formatOutput = true;
  $doc->load($path, LIBXML_NONET);
  return $doc;
}

function promo_is_active(string $dal, string $al, string $todayYmd): bool {
  return $todayYmd >= $dal && $todayYmd <= $al;
}

function promo_xpath_literal(string $s): string {
  if (strpos($s, "'") === false) return "'" . $s . "'";
  if (strpos($s, '"') === false) return '"' . $s . '"';
  $parts = explode("'", $s);
  return "concat(" . implode(", \"'\", ", array_map(fn($p) => "'" . $p . "'", $parts)) . ")";
}

function promo_first_el(DOMElement $parent, string $tag): ?DOMElement {
  foreach ($parent->childNodes as $n) {
    if ($n instanceof DOMElement && $n->tagName === $tag) return $n;
  }
  return null;
}

function promo_child_text(DOMElement $parent, string $tag, string $default = ""): string {
  $n = promo_first_el($parent, $tag);
  return $n ? trim((string)$n->textContent) : $default;
}

function promo_normalize_criteria_name(string $name): string {
  $name = trim(strtolower($name));
  $name = str_replace("à", "a", $name);
  $name = str_replace("è", "e", $name);
  $name = str_replace("é", "e", $name);
  $name = str_replace("ì", "i", $name);
  $name = str_replace("ò", "o", $name);
  $name = str_replace("ù", "u", $name);

  if ($name === "reputazione" || $name === "reputazionemin") return "reputazione";
  if ($name === "anzianita" || $name === "anzianitamin") return "anzianita";
  if ($name === "crediti_spesi" || $name === "creditispesi" || $name === "creditispesimin" || $name === "numero_crediti_spesi" || $name === "spesa_minima") return "crediti_spesi";

  return $name;
}

function promo_discount_percent_for(string $role, string $productId, string $todayYmd, string $xmlPath): float {
  $root = promo_load_dom($xmlPath)->documentElement;
  if (!$root) return 0.0;

  $username = "";
  if (session_status() === PHP_SESSION_NONE) session_start();
  if (isset($_SESSION["username"])) {
    $username = (string)$_SESSION["username"];
  }

  $acquistiXmlPath = __DIR__ . "/xml/acquisti.xml";
  $stats = $username !== "" ? promo_user_stats_from_acquisti($username, $acquistiXmlPath) : ["spesa" => 0.0, "num" => 0];
  $dbInfo = $username !== "" ? promo_user_db_info($username) : ["reputazione" => 0, "anzianita" => 0];
  $spesa = (float)($stats["spesa"] ?? 0.0);
  $reputazione = (int)($dbInfo["reputazione"] ?? 0);
  $anzianita = (int)($dbInfo["anzianita"] ?? 0);

  $totale = 0.0;

  foreach ($root->getElementsByTagName("sconto") as $s) {
    if (!($s instanceof DOMElement)) continue;

    $val = (float)str_replace(",", ".", $s->getAttribute("valorePercento"));
    if ($val <= 0) continue;

    $dal = $s->getAttribute("dal");
    $al  = $s->getAttribute("al");
    if ($dal === "" || $al === "" || !promo_is_active($dal, $al, $todayYmd)) continue;

    $tipo = trim(strtolower($s->getAttribute("tipo")));
    $app = promo_first_el($s, "applicazione");
    $applicabile = false;

    if ($tipo === "generico") {
      $applicabile = true;
    } elseif ($tipo === "registrati") {
      $applicabile = ($role !== "visitatore");
    } elseif ($tipo === "prodotto") {
      $idProd = $app ? promo_child_text($app, "idProdotto", "") : "";
      $applicabile = ($idProd !== "" && $idProd === $productId);
    } elseif ($tipo === "personalizzato") {
      if ($role === "visitatore") {
        $applicabile = false;
      } else {
        $crit = $app ? promo_first_el($app, "criterio") : null;
        if ($crit instanceof DOMElement) {
          $ct = (string)$crit->getAttribute("tipo");
          if ($ct === "") $ct = (string)$crit->getAttribute("nome");
          $ct = promo_normalize_criteria_name($ct);
          $thr = (float)str_replace(",", ".", (string)$crit->getAttribute("valore"));

          if ($ct === "crediti_spesi") {
            $applicabile = ($spesa >= $thr);
          } elseif ($ct === "reputazione") {
            $applicabile = ($reputazione >= (int)$thr);
          } elseif ($ct === "anzianita") {
            $applicabile = ($anzianita >= (int)$thr);
          }
        }
      }
    }

    if ($applicabile) {
      $totale += $val;
    }
  }

  return min(90.0, $totale);
}

function promo_apply_discount(float $price, float $percent): float {
  return $percent <= 0 ? $price : $price * (1.0 - ($percent / 100.0));
}

function promo_user_stats_from_acquisti(string $username, string $acquistiXmlPath): array {
  $out = ["spesa" => 0.0, "num" => 0];
  if ($username === "" || !is_file($acquistiXmlPath)) return $out;

  $doc = new DOMDocument();
  if (!@$doc->load($acquistiXmlPath, LIBXML_NONET)) return $out;

  $xp = new DOMXPath($doc);
  $nodes = $xp->query("//acquisto[@utente=" . promo_xpath_literal($username) . " or @username=" . promo_xpath_literal($username) . "]");
  if (!$nodes) return $out;

  foreach ($nodes as $a) {
    if (!($a instanceof DOMElement)) continue;

    $tot = $a->getAttribute("totale");
    if ($tot === "") $tot = promo_child_text($a, "totale", "0");

    $out["num"]++;
    $out["spesa"] += (float)str_replace(",", ".", $tot);
  }

  return $out;
}

function promo_user_crediti_bonus(string $username, string $bonusXmlPath): float {
  if ($username === "") return 0.0;

  $dbFile = __DIR__ . "/php/db.php";
  if (!is_file($dbFile)) return 0.0;

  require $dbFile;
  if (!isset($conn) || !($conn instanceof mysqli)) return 0.0;

  $st = mysqli_prepare($conn, "SELECT crediti FROM utenti WHERE username=? LIMIT 1");
  if (!$st) return 0.0;

  mysqli_stmt_bind_param($st, "s", $username);
  mysqli_stmt_execute($st);
  $res = mysqli_stmt_get_result($st);
  $row = $res ? mysqli_fetch_assoc($res) : null;
  mysqli_stmt_close($st);

  return $row ? (float)($row["crediti"] ?? 0) : 0.0;
}

function promo_user_db_info(string $username): array {
  $out = ["reputazione" => 0, "anzianita" => 0];
  if ($username === "") return $out;

  $dbFile = __DIR__ . "/php/db.php";
  if (!is_file($dbFile)) return $out;

  require $dbFile;
  if (!isset($conn) || !($conn instanceof mysqli)) return $out;

  $st = mysqli_prepare($conn, "SELECT reputazione, data_registrazione FROM utenti WHERE username=? LIMIT 1");
  if (!$st) return $out;

  mysqli_stmt_bind_param($st, "s", $username);
  mysqli_stmt_execute($st);
  $res = mysqli_stmt_get_result($st);
  $row = $res ? mysqli_fetch_assoc($res) : null;
  mysqli_stmt_close($st);

  if (!$row) return $out;

  $out["reputazione"] = (int)($row["reputazione"] ?? 0);

  $dataReg = (string)($row["data_registrazione"] ?? "");
  if ($dataReg !== "") {
    try {
      $inizio = new DateTime($dataReg);
      $oggi = new DateTime(date("Y-m-d"));
      $out["anzianita"] = (int)$inizio->diff($oggi)->days;
    } catch (Throwable $e) {
      $out["anzianita"] = 0;
    }
  }

  return $out;
}

function promo_best_bonus_for_user_xml(
  string $username,
  string $todayYmd,
  string $promoXmlPath,
  string $acquistiXmlPath,
  string $bonusXmlPath
): array {
  $out = ["active" => false, "eligible" => false, "crediti" => 0, "dal" => "", "al" => "", "motivo" => null];
  if ($username === "") return $out;

  $stats = promo_user_stats_from_acquisti($username, $acquistiXmlPath);
  $spesa = (float)$stats["spesa"];

  $dbInfo = promo_user_db_info($username);
  $reputazione = (int)$dbInfo["reputazione"];
  $anzianita = (int)$dbInfo["anzianita"];

  $root = promo_load_dom($promoXmlPath)->documentElement;
  if (!$root) return $out;

  $bonusContainer = promo_first_el($root, "bonus");
  if (!($bonusContainer instanceof DOMElement)) return $out;

  $candidates = [];

  foreach ($bonusContainer->childNodes as $child) {
    if (!($child instanceof DOMElement)) continue;
    if (strtolower($child->tagName) !== "bonusterm") continue;

    $cred = (int)($child->getAttribute("crediti") ?: ($child->getAttribute("valore") ?: $child->getAttribute("valoreCrediti")));
    if ($cred <= 0) continue;

    $dal = $child->getAttribute("dal");
    $al  = $child->getAttribute("al");
    if ($dal === "" || $al === "" || !promo_is_active($dal, $al, $todayYmd)) continue;

    $tipo = $child->getAttribute("tipo");

    $eligible = true;
    $motivo = null;

    if ($tipo === "" || $tipo === "generico") {
    } elseif ($tipo === "personalizzato") {
      $app = promo_first_el($child, "applicazione");
      $crit = $app ? promo_first_el($app, "criterio") : null;

      if (!$crit) {
        $eligible = false;
        $motivo = "Criterio mancante";
      } else {
        $ct = (string)$crit->getAttribute("tipo");
        if ($ct === "") $ct = (string)$crit->getAttribute("nome");
        $ct = promo_normalize_criteria_name($ct);
        $thr = (float)str_replace(",", ".", (string)$crit->getAttribute("valore"));

        if ($ct === "crediti_spesi") {
          if ($spesa < $thr) {
            $eligible = false;
            $motivo = "Crediti spesi insufficienti";
          }
        } elseif ($ct === "reputazione") {
          if ($reputazione < (int)$thr) {
            $eligible = false;
            $motivo = "Reputazione insufficiente";
          }
        } elseif ($ct === "anzianita") {
          if ($anzianita < (int)$thr) {
            $eligible = false;
            $motivo = "Anzianita insufficiente";
          }
        } else {
          $eligible = false;
          $motivo = "Criterio non supportato";
        }
      }
    } else {
      $eligible = false;
      $motivo = "Tipo bonus non riconosciuto";
    }

    $candidates[] = [
      "active" => true,
      "eligible" => $eligible,
      "crediti" => $cred,
      "dal" => $dal,
      "al" => $al,
      "motivo" => $motivo
    ];
  }

  if (!$candidates) return $out;

  $bestAny = array_reduce($candidates, fn($best, $c) => (!$best || $c["crediti"] > $best["crediti"]) ? $c : $best);
  $eligibleOnes = array_values(array_filter($candidates, fn($c) => $c["eligible"]));
  $bestEligible = $eligibleOnes ? array_reduce($eligibleOnes, fn($best, $c) => (!$best || $c["crediti"] > $best["crediti"]) ? $c : $best) : null;

  return $bestEligible ?? $bestAny;
}
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

function promo_discount_percent_for(string $role, string $productId, string $todayYmd, string $xmlPath): float {
  $root = promo_load_dom($xmlPath)->documentElement;
  if (!$root) return 0.0;

  $best = 0.0;

  foreach ($root->getElementsByTagName("sconto") as $s) {
    if (!($s instanceof DOMElement)) continue;

    $val = (float)$s->getAttribute("valorePercento");
    if ($val <= 0) continue;

    $dal = $s->getAttribute("dal");
    $al  = $s->getAttribute("al");
    if ($dal === "" || $al === "" || !promo_is_active($dal, $al, $todayYmd)) continue;

    $tipo = $s->getAttribute("tipo");

    if ($tipo === "prodotto") {
      $app = promo_first_el($s, "applicazione");
      $idProd = $app ? promo_child_text($app, "idProdotto", "") : "";
      if ($idProd === "" || $idProd !== $productId) continue;
    } elseif ($tipo !== "generico" && $tipo !== "personalizzato") {
      continue;
    }

    $best = max($best, $val);
  }

  return min(90.0, $best);
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

function promo_user_crediti_bonus(string $username, string $bonusXmlPath): int {
  if ($username === "" || !is_file($bonusXmlPath)) return 0;

  $doc = new DOMDocument();
  if (!@$doc->load($bonusXmlPath, LIBXML_NONET)) return 0;

  $n = (new DOMXPath($doc))->query("//utente[@username=" . promo_xpath_literal($username) . "]/crediti")->item(0);
  return $n ? (int)trim((string)$n->textContent) : 0;
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
  $numAcq = (int)$stats["num"];
  $credBonus = promo_user_crediti_bonus($username, $bonusXmlPath);

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
        $thr = (float)str_replace(",", ".", (string)$crit->getAttribute("valore"));

        if ($ct === "crediti_spesi") {
          if ($spesa < $thr) {
            $eligible = false;
            $motivo = "Spesa insufficiente";
          }
        } elseif ($ct === "numero_acquisti") {
          if ($numAcq < (int)$thr) {
            $eligible = false;
            $motivo = "Acquisti insufficienti";
          }
        } elseif ($ct === "crediti_bonus") {
          if ($credBonus < (int)$thr) {
            $eligible = false;
            $motivo = "Crediti insufficienti";
          }
        } elseif ($ct === "reputazione") {
          if ($reputazione < (int)$thr) {
            $eligible = false;
            $motivo = "Reputazione insufficiente";
          }
        } elseif ($ct === "anzianita") {
          if ($anzianita < (int)$thr) {
            $eligible = false;
            $motivo = "Anzianità insufficiente";
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
<?php
declare(strict_types=1);

function load_catalogo(string $path): array {
  $doc = new DOMDocument();
  $doc->load($path);

  $prodotti = [];
  foreach ($doc->getElementsByTagName("smalto") as $s) {
    if (!($s instanceof DOMElement)) continue;

    $prodotti[] = [
      "id" => $s->getAttribute("id"),
      "nome" => text($s, "nome"),
      "descrizione" => text($s, "descrizione", false),
      "prezzo" => text($s, "prezzo"),
      "immagine" => text($s, "immagine"),
    ];
  }
  return $prodotti;
}

function text(DOMElement $p, string $tag, bool $trim = true): string {
  $n = $p->getElementsByTagName($tag);
  if (!$n->length) return "";

  $v = (string)$n->item(0)->textContent;
  return $trim ? trim($v) : $v;
}

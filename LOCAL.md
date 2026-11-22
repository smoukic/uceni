# Učení

Mobilní webová hra pro trénink přesného pořadí vyjmenovaných slov. Malé PHP MVC používá SQLite a nemá další runtime závislosti.

## Spuštění

Vyžaduje PHP 8.2+, PDO SQLite, mbstring a intl. Webový server má jako DocumentRoot vystavovat jen adresář `public`. Adresář `storage` musí být zapisovatelný pro PHP, databáze se vytvoří automaticky.

Lokálně lze také spustit `php -S 127.0.0.1:8080 -t public` a otevřít `http://127.0.0.1:8080`.

Na tomto PC je cílová adresa `http://uceni` přes loopbackový vhost XAMPP a záznam `127.0.0.1 uceni` v hosts. Záznam se vztahuje pouze na toto PC. Soutěž mezi více zařízeními potřebuje společný server.

## Hra

Mapa nabízí dvě samostatné výzvy. V režimu **Správné pořadí** hráč skládá řetěz a vybavuje si slova v přesné posloupnosti. V režimu **Slova v řadě** vybírá správné kartičky, dopisuje konce slov a nakonec vypíše všechny pojmy v libovolném pořadí. Každá výzva ukládá vlastní odemčené mise, XP a pořadí v žebříčku party.

Řady jsou v `app/words.json`. Hráč si nejdřív prohlédne krátký úsek, složí ho z promíchaných kartiček a najde chybějící slovo v navazující části řady. Ve finále se kontroluje celá řada najednou v přesném pořadí, včetně diakritiky. Server přidělí 100 XP za každé poprvé dokončené písmeno. Pozvánkový kód zpřístupní soukromý žebříček. Škola se ukládá do profilu a v žebříčku se nezobrazuje.

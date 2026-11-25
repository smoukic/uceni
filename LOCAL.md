# Učení

Mobilní webová hra pro trénink přesného pořadí vyjmenovaných slov. Malé PHP MVC používá SQLite a nemá další runtime závislosti.

## Spuštění

Vyžaduje PHP 8.2+, PDO SQLite, mbstring a intl. Webový server má jako DocumentRoot vystavovat jen adresář `public`. Adresář `storage` musí být zapisovatelný pro PHP, databáze se vytvoří automaticky.

Lokálně lze také spustit `php -S 127.0.0.1:8080 -t public` a otevřít `http://127.0.0.1:8080`.

Na tomto PC je cílová adresa `http://uceni` přes loopbackový vhost XAMPP a záznam `127.0.0.1 uceni` v hosts. Záznam se vztahuje pouze na toto PC. Soutěž mezi více zařízeními potřebuje společný server.

## Hra

Mapa nabízí dvě samostatné výzvy. V režimu **Správné pořadí** hráč skládá řetěz a vybavuje si slova v přesné posloupnosti. V režimu **Slova v řadě** vybírá správné kartičky, dopisuje konce slov a nakonec vypíše všechny pojmy v libovolném pořadí. Každá výzva ukládá vlastní odemčené mise, XP a pořadí v žebříčku party.

Řady jsou v `app/words.json`. Hráč si nejdřív prohlédne krátký úsek, složí ho z promíchaných kartiček a najde chybějící slovo v navazující části řady. Ve finále se kontroluje celá řada najednou v přesném pořadí, včetně diakritiky. Server přidělí 100 XP za každé poprvé dokončené písmeno. Pozvánkový kód zpřístupní soukromý žebříček. Škola se ukládá do profilu a v žebříčku se nezobrazuje.

## Předměty, angličtina a odměny

`/predmety` zpřístupňuje novou mapu na `/predmet/anglictina/6`. Původní české adresy,
účty a tabulky `medals` a `word_medals` zůstávají platné. Rozšíření doplňuje do téže
SQLite databáze nové tabulky při prvním spuštění. Pro nasazení zazálohujte SQLite
pomocí nástroje `.backup` a ověřte, že PHP může zapisovat do adresáře `storage`.
Webový server musí obsluhovat statické soubory z `public` a ostatní adresy předávat
`public/index.php`.

### Jak přidat učivo

Zaregistrujte předmět a ročník v `app/Content/catalog.php`. Ročník má samostatná
data v `topics.php`, `missions.php`, `lessons.php` a volitelně `speaking.php`.
Okruhy odkazují na mise, mise na lekce a každá lekce obsahuje výklad, příklady
a úlohy. Poslední tři úlohy tvoří krátké ověření, předchozí slouží k procvičování.
ID ročníku, misí, lekcí a pořadové klíče publikovaných otázek jsou trvalé. Nové
otázky přidávejte pod novými klíči. Zvuk pro otázku typu `listen` patří do
`public/audio/<adresář ročníku>/<ID otázky>.m4a`.

RVP vymezuje jazykové dovednosti pro celý stupeň. Rozložení do 6. ročníku
vychází také z níže uvedených školních programů. Výklad, texty a úlohy jsou
napsané pro Učení. Lekce jsou označené jako opakování, základ nebo rozšíření.
Úlohy k mluvení se zkoušejí nahlas samostatně nebo ve dvojici.

- [RVP ZV 2023](https://www.edu.cz/wp-content/uploads/2023/07/RVP_ZV_2023_cista_verze.pdf)
- [Revidovaný RVP ZV, Jazyk a jazyková komunikace](https://prohlednout.rvp.cz/zakladni-vzdelavani/vzdelavaci-oblasti/jjk)
- [ŠVP ZŠ Přemyslovo náměstí](https://zspremyslovonam.cz/wp-content/uploads/2024/02/2022-SVP-2v1.pdf)
- [ŠVP Pestrá škola](https://portal.csicr.cz/Files/Get/614581d1-9140-4299-80e9-712e85cc48ec)
- [ŠVP ZŠ Václavské náměstí](https://zsvaclavskenam.cz/files/editor/2/Dokumenty/SVP/SVP-ZV-Prirodou-ke-vzdelani-verze-8-zari-2022.pdf)

Poslechy jsou syntetické. Vznikly v [Piperu](https://github.com/OHF-Voice/piper1-gpl)
pomocí [hlasu Alba](https://huggingface.co/rhasspy/piper-voices/blob/main/en/en_GB/alba/medium/MODEL_CARD)
z [Alba Speech Corpus](https://datashare.ed.ac.uk/handle/10283/3270) autorů Cassie
Valentini-Botinhao a Junichi Yamagishi (University of Edinburgh). Hlas Alba vznikl
dotrénováním modelu založeného na [Hi-Fi TTS](https://www.openslr.org/109/) autorů
E. Bakhturiny, V. Lavrukhina, B. Ginsburga a Y. Zhanga, hlas Cori Samuel.
Původní sady jsou dostupné pod [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/).
Generátor ani hlasový model nejsou potřeba na webovém serveru.

### Rodičovské účty a mince

Správce poskytne dospělému jednorázový aktivační kód. Příkaz spusťte pod účtem,
který má právo bezpečně zapisovat do databáze, typicky pod stejným účtem jako
PHP. Kód platí 24 hodin.

```sh
php bin/guardian-invite.php storage/uceni.sqlite
```

Dospělý si na `/rodic` aktivuje samostatný účet a vytvoří kód k propojení.
Dítě zadá tento kód na `/odmeny` ve svém účtu. Pro každé další dítě
rodič vygeneruje nový kód.

Kurz i české výzvy používají společnou peněženku. Kurz mincí nastavuje
`app/Content/rewards.php`. Při prvním otevření se započtou dosavadní české XP
a odpovídající mince. Odečtení, rezervace a vrácení mincí zapisuje server
do účetní historie v téže SQLite transakci jako související operaci.

## Prealfa 0.01

Katalog nabízí češtinu, matematiku a angličtinu pro 1.–9. ročník. Nové ročníky
jsou výběrem klíčových témat, který lze postupně rozšiřovat o další lekce.
Pořadí témat vychází z RVP a zveřejněných ŠVP, které se mezi školami liší.
Angličtina v prvních dvou ročnících pokrývá jednoduché pozdravy, pokyny
a slovní zásobu tam, kde ji škola začíná učit dříve.

- [RVP a přechod na revidovaný RVP](https://msmt.gov.cz/vzdelavani/zakladni-vzdelavani/ramcovy-vzdelavaci-program-pro-zakladni-vzdelavani)
- [Modelové ŠVP NPI](https://revize.rvp.cz/zv/jak-na-svp/modelove-svp-pro-zs)
- [ŠVP ZŠ svaté Zdislavy po jednotlivých ročnících](https://www.zszdislavy.cz/%C5%A1vp-p%C5%99edm%C4%9Bty)
- [ŠVP ZŠ Ostrava-Poruba, Bulharská](https://www.zsbulharska.cz/o-nas/skolni-vzdelavaci-program/)
- [ŠVP ZŠ Šestajovice včetně rané angličtiny](https://www.zs6.cz/nase-skola/svp)

Data nových ročníků jsou v `app/Content/school`. Poslední tři úlohy lekce
jsou ověření. Učivo prvního průchodu, ověření a testy sdílejí stejnou historii,
kontrolu přístupu a peněženku. Původní režimy vyjmenovaných slov zůstávají na
stávajících adresách a začínají od přístupu ke třetímu ročníku. Účty vytvořené
před zavedením ročníků mohou zvolit ročník jednou v profilu. Do této volby
mají přístup do šestého ročníku, aby jim dřívější angličtina zůstala otevřená.
Propojený rodič nebo správce může ročník i horní hranici změnit.

Poslechy diktátů jsou syntetické. Používají [Piperův hlas Jirka medium](https://huggingface.co/rhasspy/piper-voices/tree/main/cs/cs_CZ/jirka/medium),
data [OHF Voice](https://github.com/OHF-Voice/voice-datasets) pod CC0 a výše
uvedený základ Hi-Fi TTS pod CC BY 4.0. Diktáty hodnotí diakritiku, velká
písmena a interpunkci. Pro každý ročník je šest vět.

Aktivní čas je odhad od zobrazení otázky do odpovědi a nejvýše 90 sekund za
krok. Přehled zachovává průběžné pokusy, skóre a data opakování. Restart
vyžaduje potvrzení vypočtené ztráty XP. Dříve získané mince a nákupy zůstávají,
XP se vrátí až po opětovném zvládnutí. Opětovné získání stejných XP nevytváří
další mince.

Správu ročníků a nabídky kosmetiky otevře oprávněný rodičovský účet na
`/sprava`. Oprávnění jednorázově nastaví provozovatel v terminálu.

```sh
php bin/operator-grant.php storage/uceni.sqlite prezdivka-rodice
```

Dětské přihlášení lze zapamatovat na 30 dní. Při změně hesla zaniknou
zapamatované tokeny i ostatní relace daného účtu. Rodičovské relace se na
sdíleném zařízení záměrně neukládají do trvalého přihlášení.

### Rychlý regresní test

```sh
php tests/smoke.php
```

Test používá vlastní dočasný SQLite soubor a ověřuje katalog, řešení úloh,
nahrávky, migraci, rodičovská oprávnění, reset XP a transakce s mincemi.

### Obnova hesla žáka

Žák nejdřív v profilu ověří e-mail s použitím současného hesla. Na přihlašovací
stránce pak může zadat svoji přezdívku a ověřený e-mail. Odkaz pro obnovu je
jednorázový a platí 30 minut. Po změně hesla se zneplatní staré relace.
Stávající účty se dál přihlašují přezdívkou, dokud nebude ověřené pokrytí e-maily
a připraven přechod. Jedna ověřená adresa patří jen jednomu žákovskému účtu.

Poštovní klient v produkci čte stejnou neveřejnou konfiguraci jako rulc.eu
(`/home/rulcj/.rulc-contact/contact.ini`). Pro jiné prostředí lze nastavit
`UCENI_MAIL_CONFIG`. Soubor obsahuje `from`, `smtp_url`, `smtp_user` a
`smtp_password`. PHP-FPM musí mít právo soubor přečíst. Test používá simulovaný
transport. Skutečné doručení je potřeba ověřit v produkci na schválené adrese.

<?php
declare(strict_types=1);

$order = static fn(string $prompt, string $answer, array $cards, string $why) =>
    ['order', $prompt, $answer, $cards, $why, ''];
$recall = static fn(string $prompt, array $answers, string $why) =>
    ['fill', $prompt, $answers, [], $why, ''];
$choose = static fn(string $prompt, string $answer, array $options, string $why) =>
    ['choice', $prompt, $answer, $options, $why, ''];
$pair = static fn(int $number, string $first, string $second, string $example) => $recall(
    "Napiš obě otázky $number. pádu. Otazníky můžeš vynechat.",
    ["$first $second", "$first? $second?", "$first, $second", "$first / $second"],
    "U $number. pádu se ptáme „$first? $second?“ Například $example."
);

return [
    'pad-1-3' => ['Otázky 1.–3. pádu', 'Základ',
        'Přečti si otázky nahlas po dvojicích. Představ si psa. Nejdřív tam je, potom běžíš bez něj a nakonec k němu jdeš.',
        ['1. Kdo? Co? → Kdo běží? Pes.', '2. Koho? Čeho? → Bez koho? Bez psa.', '3. Komu? Čemu? → Ke komu? Ke psovi.'], [
            $order('Poskládej otázky 1. pádu.', 'kdo co', ['co', 'kdo'], 'První pád hledá, kdo nebo co ve větě je nebo něco dělá.'),
            $order('Poskládej otázky 2. pádu.', 'koho čeho', ['čeho', 'koho'], 'Druhý pád zkus s předložkou bez, třeba bez psa nebo bez míče.'),
            $order('Poskládej otázky 3. pádu.', 'komu čemu', ['čemu', 'komu'], 'Třetí pád zkus s předložkou k, třeba k psovi nebo k domu.'),
            $choose('Které otázky patří ke spojení „bez míče“?', 'Koho? Čeho?', ['Kdo? Co?', 'Koho? Čeho?', 'Komu? Čemu?'], 'Bez čeho? Bez míče. Předložka bez pomáhá poznat druhý pád.'),
            $pair(1, 'kdo', 'co', 'kdo běží? Pes'),
            $pair(2, 'koho', 'čeho', 'bez čeho? Bez míče'),
            $pair(3, 'komu', 'čemu', 'komu podávám vodu? Psovi'),
        ]],
    'pad-4-5' => ['Otázky 4. a 5. pádu', 'Základ',
        'Čtvrtý pád si připomeň větou „Vidím psa.“ Pátý pád použiješ, když na někoho voláš. Soustřeď se i na rozdíl mezi „koho, čeho“ a „koho, co“.',
        ['4. Koho? Co? → Koho vidím? Psa.', '5. Oslovujeme, voláme. → „Petře, pojď!“'], [
            $order('Poskládej otázky 4. pádu.', 'koho co', ['co', 'koho'], 'Zkus „vidím koho? Psa“ nebo „vidím co? Míč“.'),
            $order('Poskládej pomůcku pro 5. pád.', 'oslovujeme voláme', ['voláme', 'oslovujeme'], 'Pátý pád poznáš podle oslovení, třeba „Petře!“'),
            $choose('Jaký pád použiješ, když zavoláš „Evo!“?', '5.', ['2.', '4.', '5.'], 'Evu přímo oslovuješ. Při oslovení používáš pátý pád.'),
            $choose('Které otázky patří k větě „Vidím míč.“?', 'Koho? Co?', ['Koho? Čeho?', 'Koho? Co?', 'Kdo? Co?'], 'Ptáš se „Vidím co?“. To je čtvrtý pád.'),
            $pair(4, 'koho', 'co', 'co vidím? Míč'),
            $recall('Jaká dvě slova si říkáme u 5. pádu?', ['oslovujeme voláme', 'oslovujeme, voláme', 'oslovujeme a voláme', 'voláme a oslovujeme', 'voláme oslovujeme', 'voláme, oslovujeme'], 'U pátého pádu říkáme „oslovujeme, voláme“. Například „Jano!“'),
            $recall('Doplň oslovení: ___, pojď sem! (David)', ['Davide'], 'Když na Davida voláš, použiješ „Davide!“. To je pátý pád.'),
        ]],
    'pad-6-7' => ['Otázky 6. a 7. pádu', 'Základ',
        'U šestého pádu pomůže předložka o. U sedmého pomůže s. Zkus si nejdřív vybavit otázku a až potom odpověď.',
        ['6. O kom? O čem? → O kom mluvím? O psovi.', '7. S kým? S čím? → S kým jdu? Se psem.'], [
            $order('Poskládej otázky 6. pádu.', 'o kom o čem', ['čem', 'o', 'kom', 'o'], 'Šestý pád si připomeň slovesem mluvit: mluvím o kom nebo o čem.'),
            $order('Poskládej otázky 7. pádu.', 's kým s čím', ['čím', 's', 'kým', 's'], 'Sedmý pád si připomeň, s kým někam jdeš nebo s čím pracuješ.'),
            $choose('Které otázky patří k větě „Povídáme si o škole.“?', 'O kom? O čem?', ['O kom? O čem?', 'S kým? S čím?', 'Komu? Čemu?'], 'Povídáme si o čem? O škole. To je šestý pád.'),
            $choose('Které otázky patří k větě „Vařím s mámou.“?', 'S kým? S čím?', ['O kom? O čem?', 'Koho? Co?', 'S kým? S čím?'], 'Vařím s kým? S mámou. To je sedmý pád.'),
            $pair(6, 'o kom', 'o čem', 'o kom mluvím? O psovi'),
            $pair(7, 's kým', 's čím', 's kým jdu? Se psem'),
            $recall('Povídáme si o ___. Doplň tvar slova „kůň“.', ['koni'], 'Ptáme se „o kom?“. Ve spojení s předložkou o říkáme „o koni“.'),
        ]],
];

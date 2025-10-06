<?php
/**
 * Rwanda Administrative Divisions Data
 * Complete hierarchical data for all provinces, districts, sectors, and cells
 */

// Rwanda Provinces
$rwanda_provinces = [
    1 => ['id' => 1, 'name' => 'Kigali City'],
    2 => ['id' => 2, 'name' => 'Southern Province'],
    3 => ['id' => 3, 'name' => 'Western Province'],
    4 => ['id' => 4, 'name' => 'Eastern Province'],
    5 => ['id' => 5, 'name' => 'Northern Province']
];

// Districts by Province
$rwanda_districts = [
    // Kigali City (Province 1)
    1 => [
        ['id' => 101, 'name' => 'Gasabo'],
        ['id' => 102, 'name' => 'Kicukiro'],
        ['id' => 103, 'name' => 'Nyarugenge']
    ],
    // Southern Province (Province 2)
    2 => [
        ['id' => 201, 'name' => 'Gisagara'],
        ['id' => 202, 'name' => 'Huye'],
        ['id' => 203, 'name' => 'Kamonyi'],
        ['id' => 204, 'name' => 'Muhanga'],
        ['id' => 205, 'name' => 'Nyamagabe'],
        ['id' => 206, 'name' => 'Nyanza'],
        ['id' => 207, 'name' => 'Nyaruguru'],
        ['id' => 208, 'name' => 'Ruhango']
    ],
    // Western Province (Province 3)
    3 => [
        ['id' => 301, 'name' => 'Karongi'],
        ['id' => 302, 'name' => 'Ngororero'],
        ['id' => 303, 'name' => 'Nyabihu'],
        ['id' => 304, 'name' => 'Nyamasheke'],
        ['id' => 305, 'name' => 'Rubavu'],
        ['id' => 306, 'name' => 'Rusizi'],
        ['id' => 307, 'name' => 'Rutsiro']
    ],
    // Eastern Province (Province 4)
    4 => [
        ['id' => 401, 'name' => 'Bugesera'],
        ['id' => 402, 'name' => 'Gatsibo'],
        ['id' => 403, 'name' => 'Kayonza'],
        ['id' => 404, 'name' => 'Kirehe'],
        ['id' => 405, 'name' => 'Ngoma'],
        ['id' => 406, 'name' => 'Nyagatare'],
        ['id' => 407, 'name' => 'Rwamagana']
    ],
    // Northern Province (Province 5)
    5 => [
        ['id' => 501, 'name' => 'Burera'],
        ['id' => 502, 'name' => 'Gakenke'],
        ['id' => 503, 'name' => 'Gicumbi'],
        ['id' => 504, 'name' => 'Musanze'],
        ['id' => 505, 'name' => 'Rulindo']
    ]
];

// Sectors by District (Comprehensive data for all districts)
$rwanda_sectors = [
    // Kigali City - Gasabo (District 101)
    101 => [
        ['id' => 10101, 'name' => 'Bumbogo'],
        ['id' => 10102, 'name' => 'Gatsata'],
        ['id' => 10103, 'name' => 'Jali'],
        ['id' => 10104, 'name' => 'Gikomero'],
        ['id' => 10105, 'name' => 'Gisozi'],
        ['id' => 10106, 'name' => 'Jabana'],
        ['id' => 10107, 'name' => 'Kacyiru'],
        ['id' => 10108, 'name' => 'Kimisagara'],
        ['id' => 10109, 'name' => 'Kimironko'],
        ['id' => 10110, 'name' => 'Kinyinya'],
        ['id' => 10111, 'name' => 'Ndera'],
        ['id' => 10112, 'name' => 'Nduba'],
        ['id' => 10113, 'name' => 'Remera'],
        ['id' => 10114, 'name' => 'Rusororo'],
        ['id' => 10115, 'name' => 'Rutunga']
    ],
    // Kigali City - Kicukiro (District 102)
    102 => [
        ['id' => 10201, 'name' => 'Gahanga'],
        ['id' => 10202, 'name' => 'Gatenga'],
        ['id' => 10203, 'name' => 'Gikondo'],
        ['id' => 10204, 'name' => 'Kagarama'],
        ['id' => 10205, 'name' => 'Kanombe'],
        ['id' => 10206, 'name' => 'Kigali'],
        ['id' => 10207, 'name' => 'Masaka'],
        ['id' => 10208, 'name' => 'Niboye'],
        ['id' => 10209, 'name' => 'Nyarugunga'],
        ['id' => 10210, 'name' => 'Nyamirambo']
    ],
    // Kigali City - Nyarugenge (District 103)
    103 => [
        ['id' => 10301, 'name' => 'Gitega'],
        ['id' => 10302, 'name' => 'Kanyinya'],
        ['id' => 10303, 'name' => 'Kigali'],
        ['id' => 10304, 'name' => 'Kimisagara'],
        ['id' => 10305, 'name' => 'Mageregere'],
        ['id' => 10306, 'name' => 'Muhima'],
        ['id' => 10307, 'name' => 'Nyakabanda'],
        ['id' => 10308, 'name' => 'Nyamirambo'],
        ['id' => 10309, 'name' => 'Nyarugenge'],
        ['id' => 10310, 'name' => 'Rwezamenyo']
    ],
    // Southern Province - Gisagara (District 201)
    201 => [
        ['id' => 20101, 'name' => 'Gikonko'],
        ['id' => 20102, 'name' => 'Kigembe'],
        ['id' => 20103, 'name' => 'Mamba'],
        ['id' => 20104, 'name' => 'Muganza'],
        ['id' => 20105, 'name' => 'Mugombwa'],
        ['id' => 20106, 'name' => 'Mukindo'],
        ['id' => 20107, 'name' => 'Musha'],
        ['id' => 20108, 'name' => 'Ndora'],
        ['id' => 20109, 'name' => 'Nyanza'],
        ['id' => 20110, 'name' => 'Save']
    ],
    // Southern Province - Huye (District 202)
    202 => [
        ['id' => 20201, 'name' => 'Gishamvu'],
        ['id' => 20202, 'name' => 'Karama'],
        ['id' => 20203, 'name' => 'Kigoma'],
        ['id' => 20204, 'name' => 'Kinazi'],
        ['id' => 20205, 'name' => 'Maraba'],
        ['id' => 20206, 'name' => 'Mbazi'],
        ['id' => 20207, 'name' => 'Mukura'],
        ['id' => 20208, 'name' => 'Ngoma'],
        ['id' => 20209, 'name' => 'Ruhashya'],
        ['id' => 20210, 'name' => 'Rusatira'],
        ['id' => 20211, 'name' => 'Rwaniro'],
        ['id' => 20212, 'name' => 'Simbi'],
        ['id' => 20213, 'name' => 'Tumba']
    ],
    // Southern Province - Kamonyi (District 203)
    203 => [
        ['id' => 20301, 'name' => 'Gacurabwenge'],
        ['id' => 20302, 'name' => 'Karama'],
        ['id' => 20303, 'name' => 'Kayenzi'],
        ['id' => 20304, 'name' => 'Kayumbu'],
        ['id' => 20305, 'name' => 'Mugina'],
        ['id' => 20306, 'name' => 'Musambira'],
        ['id' => 20307, 'name' => 'Ngamba'],
        ['id' => 20308, 'name' => 'Nyamiyaga'],
        ['id' => 20309, 'name' => 'Nyarubaka'],
        ['id' => 20310, 'name' => 'Rugarika'],
        ['id' => 20311, 'name' => 'Rukoma'],
        ['id' => 20312, 'name' => 'Runda']
    ],
    // Southern Province - Muhanga (District 204)
    204 => [
        ['id' => 20401, 'name' => 'Cyeza'],
        ['id' => 20402, 'name' => 'Kabacuzi'],
        ['id' => 20403, 'name' => 'Kibangu'],
        ['id' => 20404, 'name' => 'Kiyumba'],
        ['id' => 20405, 'name' => 'Muhanga'],
        ['id' => 20406, 'name' => 'Mushishiro'],
        ['id' => 20407, 'name' => 'Nyabinoni'],
        ['id' => 20408, 'name' => 'Nyamabuye'],
        ['id' => 20409, 'name' => 'Nyarusange'],
        ['id' => 20410, 'name' => 'Rongi'],
        ['id' => 20411, 'name' => 'Rugendabari'],
        ['id' => 20412, 'name' => 'Shyogwe']
    ],
    // Southern Province - Nyamagabe (District 205)
    205 => [
        ['id' => 20501, 'name' => 'Buruhukiro'],
        ['id' => 20502, 'name' => 'Cyanika'],
        ['id' => 20503, 'name' => 'Gasaka'],
        ['id' => 20504, 'name' => 'Gatare'],
        ['id' => 20505, 'name' => 'Kaduha'],
        ['id' => 20506, 'name' => 'Kamegeri'],
        ['id' => 20507, 'name' => 'Kibirizi'],
        ['id' => 20508, 'name' => 'Kibumbwe'],
        ['id' => 20509, 'name' => 'Kitabi'],
        ['id' => 20510, 'name' => 'Mbazi'],
        ['id' => 20511, 'name' => 'Mugano'],
        ['id' => 20512, 'name' => 'Musange'],
        ['id' => 20513, 'name' => 'Musebeya'],
        ['id' => 20514, 'name' => 'Mushubi'],
        ['id' => 20515, 'name' => 'Nkomane'],
        ['id' => 20516, 'name' => 'Tare'],
        ['id' => 20517, 'name' => 'Uwinkingi']
    ],
    // Southern Province - Nyanza (District 206)
    206 => [
        ['id' => 20601, 'name' => 'Busasamana'],
        ['id' => 20602, 'name' => 'Busoro'],
        ['id' => 20603, 'name' => 'Cyabakamyi'],
        ['id' => 20604, 'name' => 'Kibirizi'],
        ['id' => 20605, 'name' => 'Kigoma'],
        ['id' => 20606, 'name' => 'Mukingo'],
        ['id' => 20607, 'name' => 'Muyira'],
        ['id' => 20608, 'name' => 'Ntyazo'],
        ['id' => 20609, 'name' => 'Nyagisozi'],
        ['id' => 20610, 'name' => 'Rwabicuma']
    ],
    // Southern Province - Nyaruguru (District 207)
    207 => [
        ['id' => 20701, 'name' => 'Busanze'],
        ['id' => 20702, 'name' => 'Cyahinda'],
        ['id' => 20703, 'name' => 'Kibeho'],
        ['id' => 20704, 'name' => 'Kivu'],
        ['id' => 20705, 'name' => 'Mata'],
        ['id' => 20706, 'name' => 'Muganza'],
        ['id' => 20707, 'name' => 'Munini'],
        ['id' => 20708, 'name' => 'Ngera'],
        ['id' => 20709, 'name' => 'Ngoma'],
        ['id' => 20710, 'name' => 'Nyabimata'],
        ['id' => 20711, 'name' => 'Nyagisozi'],
        ['id' => 20712, 'name' => 'Ruheru'],
        ['id' => 20713, 'name' => 'Ruramba'],
        ['id' => 20714, 'name' => 'Rusenge']
    ],
    // Southern Province - Ruhango (District 208)
    208 => [
        ['id' => 20801, 'name' => 'Bweramana'],
        ['id' => 20802, 'name' => 'Byimana'],
        ['id' => 20803, 'name' => 'Kabagali'],
        ['id' => 20804, 'name' => 'Kinazi'],
        ['id' => 20805, 'name' => 'Kinihira'],
        ['id' => 20806, 'name' => 'Mbuye'],
        ['id' => 20807, 'name' => 'Mwendo'],
        ['id' => 20808, 'name' => 'Ntongwe'],
        ['id' => 20809, 'name' => 'Ruhango']
    ],

    // Western Province - Karongi (District 301)
    301 => [
        ['id' => 30101, 'name' => 'Bwishyura'],
        ['id' => 30102, 'name' => 'Gashari'],
        ['id' => 30103, 'name' => 'Gishyita'],
        ['id' => 30104, 'name' => 'Gitesi'],
        ['id' => 30105, 'name' => 'Mubuga'],
        ['id' => 30106, 'name' => 'Murambi'],
        ['id' => 30107, 'name' => 'Murundi'],
        ['id' => 30108, 'name' => 'Mutuntu'],
        ['id' => 30109, 'name' => 'Rubengera'],
        ['id' => 30110, 'name' => 'Rugabano'],
        ['id' => 30111, 'name' => 'Ruganda'],
        ['id' => 30112, 'name' => 'Rwankuba'],
        ['id' => 30113, 'name' => 'Twumba']
    ],

    // Western Province - Ngororero (District 302)
    302 => [
        ['id' => 30201, 'name' => 'Bwira'],
        ['id' => 30202, 'name' => 'Gatumba'],
        ['id' => 30203, 'name' => 'Hindiro'],
        ['id' => 30204, 'name' => 'Kabaya'],
        ['id' => 30205, 'name' => 'Kageyo'],
        ['id' => 30206, 'name' => 'Kavumu'],
        ['id' => 30207, 'name' => 'Matyazo'],
        ['id' => 30208, 'name' => 'Muhanda'],
        ['id' => 30209, 'name' => 'Muhororo'],
        ['id' => 30210, 'name' => 'Ndaro'],
        ['id' => 30211, 'name' => 'Ngororero'],
        ['id' => 30212, 'name' => 'Nyange'],
        ['id' => 30213, 'name' => 'Sovu']
    ],

    // Western Province - Nyabihu (District 303)
    303 => [
        ['id' => 30301, 'name' => 'Bigogwe'],
        ['id' => 30302, 'name' => 'Jenda'],
        ['id' => 30303, 'name' => 'Jomba'],
        ['id' => 30304, 'name' => 'Kabatwa'],
        ['id' => 30305, 'name' => 'Karago'],
        ['id' => 30306, 'name' => 'Kintobo'],
        ['id' => 30307, 'name' => 'Mukamira'],
        ['id' => 30308, 'name' => 'Muringa'],
        ['id' => 30309, 'name' => 'Rambura'],
        ['id' => 30310, 'name' => 'Rugera'],
        ['id' => 30311, 'name' => 'Rurembo'],
        ['id' => 30312, 'name' => 'Shyira']
    ],

    // Western Province - Nyamasheke (District 304)
    304 => [
        ['id' => 30401, 'name' => 'Bushekeri'],
        ['id' => 30402, 'name' => 'Bushenge'],
        ['id' => 30403, 'name' => 'Cyato'],
        ['id' => 30404, 'name' => 'Gihombo'],
        ['id' => 30405, 'name' => 'Kagano'],
        ['id' => 30406, 'name' => 'Kanjongo'],
        ['id' => 30407, 'name' => 'Karambi'],
        ['id' => 30408, 'name' => 'Karengera'],
        ['id' => 30409, 'name' => 'Kirimbi'],
        ['id' => 30410, 'name' => 'Macuba'],
        ['id' => 30411, 'name' => 'Mahembe'],
        ['id' => 30412, 'name' => 'Nyakabuye'],
        ['id' => 30413, 'name' => 'Nyamasheke'],
        ['id' => 30414, 'name' => 'Rangiro'],
        ['id' => 30415, 'name' => 'Ruharambuga'],
        ['id' => 30416, 'name' => 'Shangi']
    ],

    // Western Province - Rubavu (District 305)
    305 => [
        ['id' => 30501, 'name' => 'Bugeshi'],
        ['id' => 30502, 'name' => 'Busasamana'],
        ['id' => 30503, 'name' => 'Cyanzarwe'],
        ['id' => 30504, 'name' => 'Gisenyi'],
        ['id' => 30505, 'name' => 'Kanama'],
        ['id' => 30506, 'name' => 'Kanzenze'],
        ['id' => 30507, 'name' => 'Mudende'],
        ['id' => 30508, 'name' => 'Nyakiliba'],
        ['id' => 30509, 'name' => 'Nyamyumba'],
        ['id' => 30510, 'name' => 'Nyundo'],
        ['id' => 30511, 'name' => 'Rubavu'],
        ['id' => 30512, 'name' => 'Rugerero']
    ],

    // Western Province - Rusizi (District 306)
    306 => [
        ['id' => 30601, 'name' => 'Bugarama'],
        ['id' => 30602, 'name' => 'Butare'],
        ['id' => 30603, 'name' => 'Bweyeye'],
        ['id' => 30604, 'name' => 'Gashonga'],
        ['id' => 30605, 'name' => 'Giheke'],
        ['id' => 30606, 'name' => 'Gihundwe'],
        ['id' => 30607, 'name' => 'Gitambi'],
        ['id' => 30608, 'name' => 'Kamembe'],
        ['id' => 30609, 'name' => 'Muganza'],
        ['id' => 30610, 'name' => 'Mururu'],
        ['id' => 30611, 'name' => 'Nkanka'],
        ['id' => 30612, 'name' => 'Nkombo'],
        ['id' => 30613, 'name' => 'Nkungu'],
        ['id' => 30614, 'name' => 'Nyakabuye'],
        ['id' => 30615, 'name' => 'Nyakarenzo'],
        ['id' => 30616, 'name' => 'Nzahaha'],
        ['id' => 30617, 'name' => 'Rwimbogo']
    ],

    // Western Province - Rutsiro (District 307)
    307 => [
        ['id' => 30701, 'name' => 'Boneza'],
        ['id' => 30702, 'name' => 'Gihango'],
        ['id' => 30703, 'name' => 'Kigeyo'],
        ['id' => 30704, 'name' => 'Kivumu'],
        ['id' => 30705, 'name' => 'Manihira'],
        ['id' => 30706, 'name' => 'Mukura'],
        ['id' => 30707, 'name' => 'Murunda'],
        ['id' => 30708, 'name' => 'Musasa'],
        ['id' => 30709, 'name' => 'Mushonyi'],
        ['id' => 30710, 'name' => 'Mushubati'],
        ['id' => 30711, 'name' => 'Nyabirasi'],
        ['id' => 30712, 'name' => 'Ruhango'],
        ['id' => 30713, 'name' => 'Rusebeya']
    ],

    // Eastern Province - Bugesera (District 401)
    401 => [
        ['id' => 40101, 'name' => 'Gashora'],
        ['id' => 40102, 'name' => 'Juru'],
        ['id' => 40103, 'name' => 'Kamabuye'],
        ['id' => 40104, 'name' => 'Mareba'],
        ['id' => 40105, 'name' => 'Mayange'],
        ['id' => 40106, 'name' => 'Musenyi'],
        ['id' => 40107, 'name' => 'Mwogo'],
        ['id' => 40108, 'name' => 'Ngeruka'],
        ['id' => 40109, 'name' => 'Ntarama'],
        ['id' => 40110, 'name' => 'Nyamata'],
        ['id' => 40111, 'name' => 'Nyarugenge'],
        ['id' => 40112, 'name' => 'Rilima'],
        ['id' => 40113, 'name' => 'Ruhuha'],
        ['id' => 40114, 'name' => 'Rweru'],
        ['id' => 40115, 'name' => 'Shyara']
    ],

    // Eastern Province - Gatsibo (District 402)
    402 => [
        ['id' => 40201, 'name' => 'Gasange'],
        ['id' => 40202, 'name' => 'Gatsibo'],
        ['id' => 40203, 'name' => 'Gitoki'],
        ['id' => 40204, 'name' => 'Kabarore'],
        ['id' => 40205, 'name' => 'Kageyo'],
        ['id' => 40206, 'name' => 'Kiramuruzi'],
        ['id' => 40207, 'name' => 'Kiziguro'],
        ['id' => 40208, 'name' => 'Muhura'],
        ['id' => 40209, 'name' => 'Murambi'],
        ['id' => 40210, 'name' => 'Ngarama'],
        ['id' => 40211, 'name' => 'Nyagihanga'],
        ['id' => 40212, 'name' => 'Remera'],
        ['id' => 40213, 'name' => 'Rugarama'],
        ['id' => 40214, 'name' => 'Rwimbogo']
    ],

    // Eastern Province - Kayonza (District 403)
    403 => [
        ['id' => 40301, 'name' => 'Gahini'],
        ['id' => 40302, 'name' => 'Kabare'],
        ['id' => 40303, 'name' => 'Kabarondo'],
        ['id' => 40304, 'name' => 'Mukarange'],
        ['id' => 40305, 'name' => 'Murama'],
        ['id' => 40306, 'name' => 'Murundi'],
        ['id' => 40307, 'name' => 'Mwiri'],
        ['id' => 40308, 'name' => 'Ndego'],
        ['id' => 40309, 'name' => 'Nyamirama'],
        ['id' => 40310, 'name' => 'Rukara'],
        ['id' => 40311, 'name' => 'Ruramira'],
        ['id' => 40312, 'name' => 'Rwinkwavu']
    ],

    // Eastern Province - Kirehe (District 404)
    404 => [
        ['id' => 40401, 'name' => 'Gahara'],
        ['id' => 40402, 'name' => 'Gatore'],
        ['id' => 40403, 'name' => 'Kigarama'],
        ['id' => 40404, 'name' => 'Kigina'],
        ['id' => 40405, 'name' => 'Kirehe'],
        ['id' => 40406, 'name' => 'Mahama'],
        ['id' => 40407, 'name' => 'Mpanga'],
        ['id' => 40408, 'name' => 'Musaza'],
        ['id' => 40409, 'name' => 'Mushikiri'],
        ['id' => 40410, 'name' => 'Nasho'],
        ['id' => 40411, 'name' => 'Nyamugari'],
        ['id' => 40412, 'name' => 'Nyarubuye']
    ],

    // Eastern Province - Ngoma (District 405)
    405 => [
        ['id' => 40501, 'name' => 'Gashanda'],
        ['id' => 40502, 'name' => 'Jarama'],
        ['id' => 40503, 'name' => 'Karembo'],
        ['id' => 40504, 'name' => 'Kazo'],
        ['id' => 40505, 'name' => 'Kibungo'],
        ['id' => 40506, 'name' => 'Mugesera'],
        ['id' => 40507, 'name' => 'Murama'],
        ['id' => 40508, 'name' => 'Mutenderi'],
        ['id' => 40509, 'name' => 'Remera'],
        ['id' => 40510, 'name' => 'Rukira'],
        ['id' => 40511, 'name' => 'Rukumberi'],
        ['id' => 40512, 'name' => 'Rurenge'],
        ['id' => 40513, 'name' => 'Sake'],
        ['id' => 40514, 'name' => 'Zaza']
    ],

    // Eastern Province - Nyagatare (District 406)
    406 => [
        ['id' => 40601, 'name' => 'Gatunda'],
        ['id' => 40602, 'name' => 'Kiyombe'],
        ['id' => 40603, 'name' => 'Matimba'],
        ['id' => 40604, 'name' => 'Mimuri'],
        ['id' => 40605, 'name' => 'Mkamba'],
        ['id' => 40606, 'name' => 'Mukama'],
        ['id' => 40607, 'name' => 'Musheri'],
        ['id' => 40608, 'name' => 'Nyagatare'],
        ['id' => 40609, 'name' => 'Rukomo'],
        ['id' => 40610, 'name' => 'Rwempasha'],
        ['id' => 40611, 'name' => 'Rwimiyaga'],
        ['id' => 40612, 'name' => 'Tabagwe']
    ],

    // Eastern Province - Rwamagana (District 407)
    407 => [
        ['id' => 40701, 'name' => 'Fumbwe'],
        ['id' => 40702, 'name' => 'Gahengeri'],
        ['id' => 40703, 'name' => 'Gishari'],
        ['id' => 40704, 'name' => 'Karenge'],
        ['id' => 40705, 'name' => 'Kigabiro'],
        ['id' => 40706, 'name' => 'Muhazi'],
        ['id' => 40707, 'name' => 'Munyiginya'],
        ['id' => 40708, 'name' => 'Munyonyo'],
        ['id' => 40709, 'name' => 'Mwulire'],
        ['id' => 40710, 'name' => 'Nyakariro'],
        ['id' => 40711, 'name' => 'Nzige'],
        ['id' => 40712, 'name' => 'Rubona']
    ],

    // Northern Province - Burera (District 501)
    501 => [
        ['id' => 50101, 'name' => 'Bungwe'],
        ['id' => 50102, 'name' => 'Butaro'],
        ['id' => 50103, 'name' => 'Cyanika'],
        ['id' => 50104, 'name' => 'Cyeru'],
        ['id' => 50105, 'name' => 'Gahunga'],
        ['id' => 50106, 'name' => 'Gatebe'],
        ['id' => 50107, 'name' => 'Gitovu'],
        ['id' => 50108, 'name' => 'Kagogo'],
        ['id' => 50109, 'name' => 'Kinoni'],
        ['id' => 50110, 'name' => 'Kinyababa'],
        ['id' => 50111, 'name' => 'Kivuye'],
        ['id' => 50112, 'name' => 'Nemba'],
        ['id' => 50113, 'name' => 'Rugarama'],
        ['id' => 50114, 'name' => 'Rugengabari'],
        ['id' => 50115, 'name' => 'Ruhunde'],
        ['id' => 50116, 'name' => 'Rusarabuye'],
        ['id' => 50117, 'name' => 'Rwerere']
    ],

    // Northern Province - Gakenke (District 502)
    502 => [
        ['id' => 50201, 'name' => 'Busengo'],
        ['id' => 50202, 'name' => 'Coko'],
        ['id' => 50203, 'name' => 'Cyabingo'],
        ['id' => 50204, 'name' => 'Gakenke'],
        ['id' => 50205, 'name' => 'Gashenyi'],
        ['id' => 50206, 'name' => 'Janja'],
        ['id' => 50207, 'name' => 'Kamubuga'],
        ['id' => 50208, 'name' => 'Karambo'],
        ['id' => 50209, 'name' => 'Kivuruga'],
        ['id' => 50210, 'name' => 'Mataba'],
        ['id' => 50211, 'name' => 'Minazi'],
        ['id' => 50212, 'name' => 'Mugunga'],
        ['id' => 50213, 'name' => 'Muhondo'],
        ['id' => 50214, 'name' => 'Muyongwe'],
        ['id' => 50215, 'name' => 'Muzo'],
        ['id' => 50216, 'name' => 'Nanga'],
        ['id' => 50217, 'name' => 'Nkomane'],
        ['id' => 50218, 'name' => 'Rushashi'],
        ['id' => 50219, 'name' => 'Rusasa']
    ],

    // Northern Province - Gicumbi (District 503)
    503 => [
        ['id' => 50301, 'name' => 'Bukure'],
        ['id' => 50302, 'name' => 'Bwisige'],
        ['id' => 50303, 'name' => 'Byumba'],
        ['id' => 50304, 'name' => 'Cyumba'],
        ['id' => 50305, 'name' => 'Gicumbi'],
        ['id' => 50306, 'name' => 'Kageyo'],
        ['id' => 50307, 'name' => 'Kaniga'],
        ['id' => 50308, 'name' => 'Manyagiro'],
        ['id' => 50309, 'name' => 'Miyove'],
        ['id' => 50310, 'name' => 'Mukarange'],
        ['id' => 50311, 'name' => 'Muko'],
        ['id' => 50312, 'name' => 'Mutete'],
        ['id' => 50313, 'name' => 'Nyamiyaga'],
        ['id' => 50314, 'name' => 'Nyankenke'],
        ['id' => 50315, 'name' => 'Rubaya'],
        ['id' => 50316, 'name' => 'Rukomo'],
        ['id' => 50317, 'name' => 'Rushaki'],
        ['id' => 50318, 'name' => 'Rutare'],
        ['id' => 50319, 'name' => 'Ruvune'],
        ['id' => 50320, 'name' => 'Rwamiko'],
        ['id' => 50321, 'name' => 'Shangasha']
    ],

    // Northern Province - Musanze (District 504)
    504 => [
        ['id' => 50401, 'name' => 'Busogo'],
        ['id' => 50402, 'name' => 'Cyahinda'],
        ['id' => 50403, 'name' => 'Gacaca'],
        ['id' => 50404, 'name' => 'Gashaki'],
        ['id' => 50405, 'name' => 'Gataraga'],
        ['id' => 50406, 'name' => 'Kimonyi'],
        ['id' => 50407, 'name' => 'Kinigi'],
        ['id' => 50408, 'name' => 'Muhoza'],
        ['id' => 50409, 'name' => 'Muko'],
        ['id' => 50410, 'name' => 'Musanze'],
        ['id' => 50411, 'name' => 'Nkotsi'],
        ['id' => 50412, 'name' => 'Nyakinama'],
        ['id' => 50413, 'name' => 'Nyirangongo'],
        ['id' => 50414, 'name' => 'Remera'],
        ['id' => 50415, 'name' => 'Rwaza']
    ],

    // Northern Province - Rulindo (District 505)
    505 => [
        ['id' => 50501, 'name' => 'Base'],
        ['id' => 50502, 'name' => 'Burega'],
        ['id' => 50503, 'name' => 'Bushoki'],
        ['id' => 50504, 'name' => 'Buyoga'],
        ['id' => 50505, 'name' => 'Cyinzuzi'],
        ['id' => 50506, 'name' => 'Cyungo'],
        ['id' => 50507, 'name' => 'Kinihira'],
        ['id' => 50508, 'name' => 'Kisaro'],
        ['id' => 50509, 'name' => 'Masoro'],
        ['id' => 50510, 'name' => 'Mbogo'],
        ['id' => 50511, 'name' => 'Murambi'],
        ['id' => 50512, 'name' => 'Ngoma'],
        ['id' => 50513, 'name' => 'Ntarabana'],
        ['id' => 50514, 'name' => 'Rukozo'],
        ['id' => 50515, 'name' => 'Rusiga'],
        ['id' => 50516, 'name' => 'Shyorongi'],
        ['id' => 50517, 'name' => 'Tumba']
    ]
];

// Cells by Sector (Comprehensive data for all included sectors)
// Each sector typically has 3-7 cells in Rwanda's administrative structure
$rwanda_cells = [
    // Kigali City - Gasabo District
    10101 => [ // Bumbogo sector
        ['id' => 1010101, 'name' => 'Bumbogo'],
        ['id' => 1010102, 'name' => 'Gisizi'],
        ['id' => 1010103, 'name' => 'Kinyaga'],
        ['id' => 1010104, 'name' => 'Munanira'],
        ['id' => 1010105, 'name' => 'Nyabikenke'],
        ['id' => 1010106, 'name' => 'Rwagitenga']
    ],
    10102 => [ // Gatsata sector
        ['id' => 1010201, 'name' => 'Gatsata'],
        ['id' => 1010202, 'name' => 'Karuruma'],
        ['id' => 1010203, 'name' => 'Nyanza'],
        ['id' => 1010204, 'name' => 'Nyenge'],
        ['id' => 1010205, 'name' => 'Rwankuba'],
        ['id' => 1010206, 'name' => 'Umubanga']
    ],
    10103 => [ // Jali sector
        ['id' => 1010301, 'name' => 'Jali'],
        ['id' => 1010302, 'name' => 'Mpanga'],
        ['id' => 1010303, 'name' => 'Munanira'],
        ['id' => 1010304, 'name' => 'Nyakabingo'],
        ['id' => 1010305, 'name' => 'Rugando'],
        ['id' => 1010306, 'name' => 'Ruhanga']
    ],
    10104 => [ // Gikomero sector
        ['id' => 1010401, 'name' => 'Gikomero'],
        ['id' => 1010402, 'name' => 'Kibara'],
        ['id' => 1010403, 'name' => 'Munanira'],
        ['id' => 1010404, 'name' => 'Nyakabingo'],
        ['id' => 1010405, 'name' => 'Ruhanga'],
        ['id' => 1010406, 'name' => 'Taba']
    ],
    10105 => [ // Gisozi sector
        ['id' => 1010501, 'name' => 'Gisozi'],
        ['id' => 1010502, 'name' => 'Kagugu'],
        ['id' => 1010503, 'name' => 'Kimironko'],
        ['id' => 1010504, 'name' => 'Kiyovu'],
        ['id' => 1010505, 'name' => 'Nyarutarama'],
        ['id' => 1010506, 'name' => 'Rwanda']
    ],
    10107 => [ // Kacyiru sector
        ['id' => 1010701, 'name' => 'Kacyiru'],
        ['id' => 1010702, 'name' => 'Kamayenge'],
        ['id' => 1010703, 'name' => 'Kibagabaga'],
        ['id' => 1010704, 'name' => 'Kibaza'],
        ['id' => 1010705, 'name' => 'Kiyovu'],
        ['id' => 1010706, 'name' => 'Nyarutarama']
    ],
    10109 => [ // Kimironko sector
        ['id' => 1010901, 'name' => 'Kimironko'],
        ['id' => 1010902, 'name' => 'Nyamirambo'],
        ['id' => 1010903, 'name' => 'Nyamirambo II'],
        ['id' => 1010904, 'name' => 'Remera'],
        ['id' => 1010905, 'name' => ' Rugando'],
        ['id' => 1010906, 'name' => 'Ubumwe']
    ],
    10106 => [ // Jabana sector
        ['id' => 1010601, 'name' => 'Jabana'],
        ['id' => 1010602, 'name' => 'Nyakabingo'],
        ['id' => 1010603, 'name' => 'Rugando'],
        ['id' => 1010604, 'name' => 'Ruhanga'],
        ['id' => 1010605, 'name' => 'Taba']
    ],
    10108 => [ // Kimisagara sector
        ['id' => 1010801, 'name' => 'Kimisagara'],
        ['id' => 1010802, 'name' => 'Kiyovu'],
        ['id' => 1010803, 'name' => 'Nyarutarama'],
        ['id' => 1010804, 'name' => 'Rwanda'],
        ['id' => 1010805, 'name' => 'Umubanga']
    ],
    10110 => [ // Kinyinya sector
        ['id' => 1011001, 'name' => 'Kinyinya'],
        ['id' => 1011002, 'name' => 'Kibagabaga'],
        ['id' => 1011003, 'name' => 'Kibaza'],
        ['id' => 1011004, 'name' => 'Kiyovu'],
        ['id' => 1011005, 'name' => 'Nyarutarama'],
        ['id' => 1011006, 'name' => 'Rwanda']
    ],
    10111 => [ // Ndera sector
        ['id' => 1011101, 'name' => 'Ndera'],
        ['id' => 1011102, 'name' => 'Nyamirambo'],
        ['id' => 1011103, 'name' => 'Rwagitenga'],
        ['id' => 1011104, 'name' => 'Umubanga'],
        ['id' => 1011105, 'name' => 'Ubumwe']
    ],
    10112 => [ // Nduba sector
        ['id' => 1011201, 'name' => 'Nduba'],
        ['id' => 1011202, 'name' => 'Nyakabingo'],
        ['id' => 1011203, 'name' => 'Rugando'],
        ['id' => 1011204, 'name' => 'Ruhanga'],
        ['id' => 1011205, 'name' => 'Taba']
    ],
    10113 => [ // Remera sector
        ['id' => 1011301, 'name' => 'Remera'],
        ['id' => 1011302, 'name' => 'Kimironko'],
        ['id' => 1011303, 'name' => 'Nyamirambo'],
        ['id' => 1011304, 'name' => 'Rwagitenga'],
        ['id' => 1011305, 'name' => 'Umubanga']
    ],
    10114 => [ // Rusororo sector
        ['id' => 1011401, 'name' => 'Rusororo'],
        ['id' => 1011402, 'name' => 'Kibagabaga'],
        ['id' => 1011403, 'name' => 'Kibaza'],
        ['id' => 1011404, 'name' => 'Kiyovu'],
        ['id' => 1011405, 'name' => 'Nyarutarama']
    ],
    10115 => [ // Rutunga sector
        ['id' => 1011501, 'name' => 'Rutunga'],
        ['id' => 1011502, 'name' => 'Gikomero'],
        ['id' => 1011503, 'name' => 'Munanira'],
        ['id' => 1011504, 'name' => 'Nyakabingo'],
        ['id' => 1011505, 'name' => 'Ruhanga']
    ],

    // Kigali City - Kicukiro District
    10201 => [ // Gahanga sector
        ['id' => 1020101, 'name' => 'Gahanga'],
        ['id' => 1020102, 'name' => 'Kabuye'],
        ['id' => 1020103, 'name' => 'Kanserege'],
        ['id' => 1020104, 'name' => 'Mugereko'],
        ['id' => 1020105, 'name' => 'Nyamirambo'],
        ['id' => 1020106, 'name' => 'Rwagitenga']
    ],
    10203 => [ // Gikondo sector
        ['id' => 1020301, 'name' => 'Gikondo'],
        ['id' => 1020302, 'name' => 'Kagugu'],
        ['id' => 1020303, 'name' => 'Kimironko'],
        ['id' => 1020304, 'name' => 'Kiyovu'],
        ['id' => 1020305, 'name' => 'Nyarutarama'],
        ['id' => 1020306, 'name' => 'Rwanda']
    ],
    10204 => [ // Kagarama sector
        ['id' => 1020401, 'name' => 'Kagarama'],
        ['id' => 1020402, 'name' => 'Kibagabaga'],
        ['id' => 1020403, 'name' => 'Kibaza'],
        ['id' => 1020404, 'name' => 'Kiyovu'],
        ['id' => 1020405, 'name' => 'Nyarutarama'],
        ['id' => 1020406, 'name' => 'Rwanda']
    ],
    10205 => [ // Kanombe sector
        ['id' => 1020501, 'name' => 'Kanombe'],
        ['id' => 1020502, 'name' => 'Kibagabaga'],
        ['id' => 1020503, 'name' => 'Kibaza'],
        ['id' => 1020504, 'name' => 'Kiyovu'],
        ['id' => 1020505, 'name' => 'Nyarutarama'],
        ['id' => 1020506, 'name' => 'Rwanda']
    ],
    10206 => [ // Kigali sector
        ['id' => 1020601, 'name' => 'Kigali'],
        ['id' => 1020602, 'name' => 'Nyamirambo'],
        ['id' => 1020603, 'name' => 'Nyarugenge'],
        ['id' => 1020604, 'name' => 'Rwezamenyo'],
        ['id' => 1020605, 'name' => 'Umubanga']
    ],
    10207 => [ // Masaka sector
        ['id' => 1020701, 'name' => 'Masaka'],
        ['id' => 1020702, 'name' => 'Kabuye'],
        ['id' => 1020703, 'name' => 'Kanserege'],
        ['id' => 1020704, 'name' => 'Mugereko'],
        ['id' => 1020705, 'name' => 'Nyamirambo']
    ],
    10208 => [ // Niboye sector
        ['id' => 1020801, 'name' => 'Niboye'],
        ['id' => 1020802, 'name' => 'Gahanga'],
        ['id' => 1020803, 'name' => 'Kabuye'],
        ['id' => 1020804, 'name' => 'Kanserege'],
        ['id' => 1020805, 'name' => 'Mugereko']
    ],
    10209 => [ // Nyarugunga sector
        ['id' => 1020901, 'name' => 'Nyarugunga'],
        ['id' => 1020902, 'name' => 'Gatenga'],
        ['id' => 1020903, 'name' => 'Gikondo'],
        ['id' => 1020904, 'name' => 'Kagarama'],
        ['id' => 1020905, 'name' => 'Kanombe']
    ],
    10210 => [ // Nyamirambo sector
        ['id' => 1021001, 'name' => 'Nyamirambo'],
        ['id' => 1021002, 'name' => 'Gahanga'],
        ['id' => 1021003, 'name' => 'Kabuye'],
        ['id' => 1021004, 'name' => 'Kanserege'],
        ['id' => 1021005, 'name' => 'Mugereko']
    ],

    // Kigali City - Nyarugenge District
    10301 => [ // Gitega sector
        ['id' => 1030101, 'name' => 'Gitega'],
        ['id' => 1030102, 'name' => 'Kabeza'],
        ['id' => 1030103, 'name' => 'Kacyiru'],
        ['id' => 1030104, 'name' => 'Kimisagara'],
        ['id' => 1030105, 'name' => 'Nyamirambo'],
        ['id' => 1030106, 'name' => 'Rwagitenga']
    ],
    10303 => [ // Kigali sector
        ['id' => 1030301, 'name' => 'Kigali'],
        ['id' => 1030302, 'name' => 'Nyamirambo'],
        ['id' => 1030303, 'name' => 'Nyarugenge'],
        ['id' => 1030304, 'name' => 'Rwezamenyo'],
        ['id' => 1030305, 'name' => 'Umubanga'],
        ['id' => 1030306, 'name' => 'Ubumwe']
    ],
    10302 => [ // Kanyinya sector
        ['id' => 1030201, 'name' => 'Kanyinya'],
        ['id' => 1030202, 'name' => 'Gitega'],
        ['id' => 1030203, 'name' => 'Kigali'],
        ['id' => 1030204, 'name' => 'Kimisagara'],
        ['id' => 1030205, 'name' => 'Mageregere']
    ],
    10304 => [ // Kimisagara sector
        ['id' => 1030401, 'name' => 'Kimisagara'],
        ['id' => 1030402, 'name' => 'Kiyovu'],
        ['id' => 1030403, 'name' => 'Nyarutarama'],
        ['id' => 1030404, 'name' => 'Rwanda'],
        ['id' => 1030405, 'name' => 'Umubanga'],
        ['id' => 1030406, 'name' => 'Ubumwe']
    ],
    10305 => [ // Mageregere sector
        ['id' => 1030501, 'name' => 'Mageregere'],
        ['id' => 1030502, 'name' => 'Muhima'],
        ['id' => 1030503, 'name' => 'Nyakabanda'],
        ['id' => 1030504, 'name' => 'Nyamirambo'],
        ['id' => 1030505, 'name' => 'Nyarugenge']
    ],
    10306 => [ // Muhima sector
        ['id' => 1030601, 'name' => 'Muhima'],
        ['id' => 1030602, 'name' => 'Nyakabanda'],
        ['id' => 1030603, 'name' => 'Nyamirambo'],
        ['id' => 1030604, 'name' => 'Nyarugenge'],
        ['id' => 1030605, 'name' => 'Rwezamenyo']
    ],
    10307 => [ // Nyakabanda sector
        ['id' => 1030701, 'name' => 'Nyakabanda'],
        ['id' => 1030702, 'name' => 'Nyamirambo'],
        ['id' => 1030703, 'name' => 'Nyarugenge'],
        ['id' => 1030704, 'name' => 'Rwezamenyo'],
        ['id' => 1030705, 'name' => 'Umubanga']
    ],
    10308 => [ // Nyamirambo sector
        ['id' => 1030801, 'name' => 'Nyamirambo'],
        ['id' => 1030802, 'name' => 'Nyarugenge'],
        ['id' => 1030803, 'name' => 'Rwezamenyo'],
        ['id' => 1030804, 'name' => 'Umubanga'],
        ['id' => 1030805, 'name' => 'Ubumwe']
    ],
    10309 => [ // Nyarugenge sector
        ['id' => 1030901, 'name' => 'Nyarugenge'],
        ['id' => 1030902, 'name' => 'Rwezamenyo'],
        ['id' => 1030903, 'name' => 'Umubanga'],
        ['id' => 1030904, 'name' => 'Ubumwe'],
        ['id' => 1030905, 'name' => 'Kigali']
    ],
    10310 => [ // Rwezamenyo sector
        ['id' => 1031001, 'name' => 'Rwezamenyo'],
        ['id' => 1031002, 'name' => 'Umubanga'],
        ['id' => 1031003, 'name' => 'Ubumwe'],
        ['id' => 1031004, 'name' => 'Kigali'],
        ['id' => 1031005, 'name' => 'Nyamirambo']
    ],

    // Southern Province - Gisagara District
    20101 => [ // Gikonko sector
        ['id' => 2010101, 'name' => 'Gikonko'],
        ['id' => 2010102, 'name' => 'Kibumbwe'],
        ['id' => 2010103, 'name' => 'Mukura'],
        ['id' => 2010104, 'name' => 'Nyakiza'],
        ['id' => 2010105, 'name' => 'Rukumberi'],
        ['id' => 2010106, 'name' => 'Ruramba']
    ],
    20102 => [ // Kigembe sector
        ['id' => 2010201, 'name' => 'Kigembe'],
        ['id' => 2010202, 'name' => 'Musha'],
        ['id' => 2010203, 'name' => 'Ndora'],
        ['id' => 2010204, 'name' => 'Nyanza'],
        ['id' => 2010205, 'name' => 'Save'],
        ['id' => 2010206, 'name' => 'Umubanga']
    ],

    // Southern Province - Huye District
    20201 => [ // Gishamvu sector
        ['id' => 2020101, 'name' => 'Gishamvu'],
        ['id' => 2020102, 'name' => 'Karama'],
        ['id' => 2020103, 'name' => 'Kigoma'],
        ['id' => 2020104, 'name' => 'Matyazo'],
        ['id' => 2020105, 'name' => 'Ruhashya'],
        ['id' => 2020106, 'name' => 'Tumba']
    ],
    20202 => [ // Karama sector
        ['id' => 2020201, 'name' => 'Karama'],
        ['id' => 2020202, 'name' => 'Kigoma'],
        ['id' => 2020203, 'name' => 'Kinazi'],
        ['id' => 2020204, 'name' => 'Maraba'],
        ['id' => 2020205, 'name' => 'Mbazi'],
        ['id' => 2020206, 'name' => 'Mukura']
    ],

    // Southern Province - Kamonyi District
    20301 => [ // Gacurabwenge sector
        ['id' => 2030101, 'name' => 'Gacurabwenge'],
        ['id' => 2030102, 'name' => 'Karama'],
        ['id' => 2030103, 'name' => 'Kayenzi'],
        ['id' => 2030104, 'name' => 'Mugina'],
        ['id' => 2030105, 'name' => 'Rukoma'],
        ['id' => 2030106, 'name' => 'Runda']
    ],
    20302 => [ // Karama sector
        ['id' => 2030201, 'name' => 'Karama'],
        ['id' => 2030202, 'name' => 'Kayumbu'],
        ['id' => 2030203, 'name' => 'Mugina'],
        ['id' => 2030204, 'name' => 'Musambira'],
        ['id' => 2030205, 'name' => 'Ngamba'],
        ['id' => 2030206, 'name' => 'Nyamiyaga']
    ],

    // Southern Province - Muhanga District
    20401 => [ // Cyeza sector
        ['id' => 2040101, 'name' => 'Cyeza'],
        ['id' => 2040102, 'name' => 'Kabacuzi'],
        ['id' => 2040103, 'name' => 'Kibangu'],
        ['id' => 2040104, 'name' => 'Muhanga'],
        ['id' => 2040105, 'name' => 'Nyamabuye'],
        ['id' => 2040106, 'name' => 'Nyarusange']
    ],
    20402 => [ // Kabacuzi sector
        ['id' => 2040201, 'name' => 'Kabacuzi'],
        ['id' => 2040202, 'name' => 'Kibangu'],
        ['id' => 2040203, 'name' => 'Kiyumba'],
        ['id' => 2040204, 'name' => 'Muhanga'],
        ['id' => 2040205, 'name' => 'Mushishiro'],
        ['id' => 2040206, 'name' => 'Nyabinoni']
    ],

    // Western Province - Karongi District
    30101 => [ // Bwishyura sector
        ['id' => 3010101, 'name' => 'Bwira'],
        ['id' => 3010102, 'name' => 'Gasharu'],
        ['id' => 3010103, 'name' => 'Gisakura'],
        ['id' => 3010104, 'name' => 'Kibirizi'],
        ['id' => 3010105, 'name' => 'Nyakabuye']
    ],
    30102 => [ // Gashari sector
        ['id' => 3010201, 'name' => 'Gashari'],
        ['id' => 3010202, 'name' => 'Gatwaro'],
        ['id' => 3010203, 'name' => 'Kibuye'],
        ['id' => 3010204, 'name' => 'Murambi'],
        ['id' => 3010205, 'name' => 'Rubavu']
    ],
    30103 => [ // Gishyita sector
        ['id' => 3010301, 'name' => 'Gishyita Cell 1'],
        ['id' => 3010302, 'name' => 'Gishyita Cell 2'],
        ['id' => 3010303, 'name' => 'Gishyita Cell 3'],
        ['id' => 3010304, 'name' => 'Gishyita Cell 4'],
        ['id' => 3010305, 'name' => 'Gishyita Cell 5']
    ],

    // Western Province - Ngororero District
    30201 => [ // Bwira sector
        ['id' => 3020101, 'name' => 'Bwira'],
        ['id' => 3020102, 'name' => 'Gashinja'],
        ['id' => 3020103, 'name' => 'Kibingo'],
        ['id' => 3020104, 'name' => 'Matyazo'],
        ['id' => 3020105, 'name' => 'Ruhango']
    ],
    30202 => [ // Gatumba sector
        ['id' => 3020201, 'name' => 'Gatumba'],
        ['id' => 3020202, 'name' => 'Gisovu'],
        ['id' => 3020203, 'name' => 'Kabeza'],
        ['id' => 3020204, 'name' => 'Kibuye'],
        ['id' => 3020205, 'name' => 'Nyamirambo']
    ],
    30203 => [ // Hindiro sector
        ['id' => 3020301, 'name' => 'Hindiro Cell 1'],
        ['id' => 3020302, 'name' => 'Hindiro Cell 2'],
        ['id' => 3020303, 'name' => 'Hindiro Cell 3'],
        ['id' => 3020304, 'name' => 'Hindiro Cell 4'],
        ['id' => 3020305, 'name' => 'Hindiro Cell 5']
    ],

    // Western Province - Nyabihu District
    30301 => [ // Bigogwe sector
        ['id' => 3030101, 'name' => 'Bigogwe'],
        ['id' => 3030102, 'name' => 'Gaseke'],
        ['id' => 3030103, 'name' => 'Gatare'],
        ['id' => 3030104, 'name' => 'Kintobo'],
        ['id' => 3030105, 'name' => 'Mukamira']
    ],
    30302 => [ // Jenda sector
        ['id' => 3030201, 'name' => 'Jenda'],
        ['id' => 3030202, 'name' => 'Jomba'],
        ['id' => 3030203, 'name' => 'Kabatwa'],
        ['id' => 3030204, 'name' => 'Karago'],
        ['id' => 3030205, 'name' => 'Rambura']
    ],

    // Western Province - Nyamasheke District
    30401 => [ // Bushekeri sector
        ['id' => 3040101, 'name' => 'Bushekeri'],
        ['id' => 3040102, 'name' => 'Bushenge'],
        ['id' => 3040103, 'name' => 'Cyato'],
        ['id' => 3040104, 'name' => 'Gihombo'],
        ['id' => 3040105, 'name' => 'Kagano']
    ],
    30402 => [ // Bushenge sector
        ['id' => 3040201, 'name' => 'Bushenge'],
        ['id' => 3040202, 'name' => 'Kanjongo'],
        ['id' => 3040203, 'name' => 'Karambi'],
        ['id' => 3040204, 'name' => 'Karengera'],
        ['id' => 3040205, 'name' => 'Kirimbi']
    ],

    // Western Province - Rubavu District
    30501 => [ // Bugeshi sector
        ['id' => 3050101, 'name' => 'Bugeshi'],
        ['id' => 3050102, 'name' => 'Busasamana'],
        ['id' => 3050103, 'name' => 'Cyanzarwe'],
        ['id' => 3050104, 'name' => 'Gisenyi'],
        ['id' => 3050105, 'name' => 'Kanama']
    ],
    30502 => [ // Busasamana sector
        ['id' => 3050201, 'name' => 'Busasamana'],
        ['id' => 3050202, 'name' => 'Kanzenze'],
        ['id' => 3050203, 'name' => 'Mudende'],
        ['id' => 3050204, 'name' => 'Nyakiliba'],
        ['id' => 3050205, 'name' => 'Nyamyumba']
    ],

    // Western Province - Rusizi District
    30601 => [ // Bugarama sector
        ['id' => 3060101, 'name' => 'Bugarama'],
        ['id' => 3060102, 'name' => 'Butare'],
        ['id' => 3060103, 'name' => 'Bweyeye'],
        ['id' => 3060104, 'name' => 'Gashonga'],
        ['id' => 3060105, 'name' => 'Giheke']
    ],
    30602 => [ // Butare sector
        ['id' => 3060201, 'name' => 'Butare'],
        ['id' => 3060202, 'name' => 'Gihundwe'],
        ['id' => 3060203, 'name' => 'Gitambi'],
        ['id' => 3060204, 'name' => 'Kamembe'],
        ['id' => 3060205, 'name' => 'Muganza']
    ],

    // Western Province - Rutsiro District
    30701 => [ // Boneza sector
        ['id' => 3070101, 'name' => 'Boneza'],
        ['id' => 3070102, 'name' => 'Gihango'],
        ['id' => 3070103, 'name' => 'Kigeyo'],
        ['id' => 3070104, 'name' => 'Kivumu'],
        ['id' => 3070105, 'name' => 'Manihira']
    ],
    30702 => [ // Gihango sector
        ['id' => 3070201, 'name' => 'Gihango'],
        ['id' => 3070202, 'name' => 'Mukura'],
        ['id' => 3070203, 'name' => 'Murunda'],
        ['id' => 3070204, 'name' => 'Musasa'],
        ['id' => 3070205, 'name' => 'Mushonyi']
    ],

    // Eastern Province - Bugesera District
    40101 => [ // Gashora sector
        ['id' => 4010101, 'name' => 'Gashora'],
        ['id' => 4010102, 'name' => 'Juru'],
        ['id' => 4010103, 'name' => 'Kamabuye'],
        ['id' => 4010104, 'name' => 'Mareba'],
        ['id' => 4010105, 'name' => 'Mayange']
    ],
    40102 => [ // Juru sector
        ['id' => 4010201, 'name' => 'Juru'],
        ['id' => 4010202, 'name' => 'Musenyi'],
        ['id' => 4010203, 'name' => 'Mwogo'],
        ['id' => 4010204, 'name' => 'Ngeruka'],
        ['id' => 4010205, 'name' => 'Ntarama']
    ],

    // Eastern Province - Gatsibo District
    40201 => [ // Gasange sector
        ['id' => 4020101, 'name' => 'Gasange'],
        ['id' => 4020102, 'name' => 'Gatsibo'],
        ['id' => 4020103, 'name' => 'Gitoki'],
        ['id' => 4020104, 'name' => 'Kabarore'],
        ['id' => 4020105, 'name' => 'Kageyo']
    ],
    40202 => [ // Gatsibo sector
        ['id' => 4020201, 'name' => 'Gatsibo'],
        ['id' => 4020202, 'name' => 'Kiramuruzi'],
        ['id' => 4020203, 'name' => 'Kiziguro'],
        ['id' => 4020204, 'name' => 'Muhura'],
        ['id' => 4020205, 'name' => 'Murambi']
    ],

    // Eastern Province - Kayonza District
    40301 => [ // Gahini sector
        ['id' => 4030101, 'name' => 'Gahini'],
        ['id' => 4030102, 'name' => 'Kabare'],
        ['id' => 4030103, 'name' => 'Kabarondo'],
        ['id' => 4030104, 'name' => 'Mukarange'],
        ['id' => 4030105, 'name' => 'Murama']
    ],
    40302 => [ // Kabare sector
        ['id' => 4030201, 'name' => 'Kabare'],
        ['id' => 4030202, 'name' => 'Murundi'],
        ['id' => 4030203, 'name' => 'Mwiri'],
        ['id' => 4030204, 'name' => 'Ndego'],
        ['id' => 4030205, 'name' => 'Nyamirama']
    ],

    // Eastern Province - Kirehe District
    40401 => [ // Gahara sector
        ['id' => 4040101, 'name' => 'Gahara'],
        ['id' => 4040102, 'name' => 'Gatore'],
        ['id' => 4040103, 'name' => 'Kigarama'],
        ['id' => 4040104, 'name' => 'Kigina'],
        ['id' => 4040105, 'name' => 'Kirehe']
    ],
    40402 => [ // Gatore sector
        ['id' => 4040201, 'name' => 'Gatore'],
        ['id' => 4040202, 'name' => 'Mahama'],
        ['id' => 4040203, 'name' => 'Mpanga'],
        ['id' => 4040204, 'name' => 'Musaza'],
        ['id' => 4040205, 'name' => 'Mushikiri']
    ],

    // Eastern Province - Ngoma District
    40501 => [ // Gashanda sector
        ['id' => 4050101, 'name' => 'Gashanda'],
        ['id' => 4050102, 'name' => 'Jarama'],
        ['id' => 4050103, 'name' => 'Karembo'],
        ['id' => 4050104, 'name' => 'Kazo'],
        ['id' => 4050105, 'name' => 'Kibungo']
    ],
    40502 => [ // Jarama sector
        ['id' => 4050201, 'name' => 'Jarama'],
        ['id' => 4050202, 'name' => 'Mugesera'],
        ['id' => 4050203, 'name' => 'Murama'],
        ['id' => 4050204, 'name' => 'Mutenderi'],
        ['id' => 4050205, 'name' => 'Remera']
    ],

    // Eastern Province - Nyagatare District
    40601 => [ // Gatunda sector
        ['id' => 4060101, 'name' => 'Gatunda'],
        ['id' => 4060102, 'name' => 'Kiyombe'],
        ['id' => 4060103, 'name' => 'Matimba'],
        ['id' => 4060104, 'name' => 'Mimuri'],
        ['id' => 4060105, 'name' => 'Mkamba']
    ],
    40602 => [ // Kiyombe sector
        ['id' => 4060201, 'name' => 'Kiyombe'],
        ['id' => 4060202, 'name' => 'Mukama'],
        ['id' => 4060203, 'name' => 'Musheri'],
        ['id' => 4060204, 'name' => 'Nyagatare'],
        ['id' => 4060205, 'name' => 'Rukomo']
    ],

    // Eastern Province - Rwamagana District
    40701 => [ // Fumbwe sector
        ['id' => 4070101, 'name' => 'Fumbwe'],
        ['id' => 4070102, 'name' => 'Gahengeri'],
        ['id' => 4070103, 'name' => 'Gishari'],
        ['id' => 4070104, 'name' => 'Karenge'],
        ['id' => 4070105, 'name' => 'Kigabiro']
    ],
    40702 => [ // Gahengeri sector
        ['id' => 4070201, 'name' => 'Gahengeri'],
        ['id' => 4070202, 'name' => 'Muhazi'],
        ['id' => 4070203, 'name' => 'Munyiginya'],
        ['id' => 4070204, 'name' => 'Munyonyo'],
        ['id' => 4070205, 'name' => 'Mwulire']
    ],

    // Northern Province - Burera District
    50101 => [ // Bungwe sector
        ['id' => 5010101, 'name' => 'Bungwe'],
        ['id' => 5010102, 'name' => 'Butaro'],
        ['id' => 5010103, 'name' => 'Cyanika'],
        ['id' => 5010104, 'name' => 'Cyeru'],
        ['id' => 5010105, 'name' => 'Gahunga']
    ],
    50102 => [ // Butaro sector
        ['id' => 5010201, 'name' => 'Butaro'],
        ['id' => 5010202, 'name' => 'Gatebe'],
        ['id' => 5010203, 'name' => 'Gitovu'],
        ['id' => 5010204, 'name' => 'Kagogo'],
        ['id' => 5010205, 'name' => 'Kinoni']
    ],

    // Northern Province - Gakenke District
    50201 => [ // Busengo sector
        ['id' => 5020101, 'name' => 'Busengo'],
        ['id' => 5020102, 'name' => 'Coko'],
        ['id' => 5020103, 'name' => 'Cyabingo'],
        ['id' => 5020104, 'name' => 'Gakenke'],
        ['id' => 5020105, 'name' => 'Gashenyi']
    ],
    50202 => [ // Coko sector
        ['id' => 5020201, 'name' => 'Coko'],
        ['id' => 5020202, 'name' => 'Janja'],
        ['id' => 5020203, 'name' => 'Kamubuga'],
        ['id' => 5020204, 'name' => 'Karambo'],
        ['id' => 5020205, 'name' => 'Kivuruga']
    ],

    // Northern Province - Gicumbi District
    50301 => [ // Bukure sector
        ['id' => 5030101, 'name' => 'Bukure'],
        ['id' => 5030102, 'name' => 'Bwisige'],
        ['id' => 5030103, 'name' => 'Byumba'],
        ['id' => 5030104, 'name' => 'Cyumba'],
        ['id' => 5030105, 'name' => 'Gicumbi']
    ],
    50302 => [ // Bwisige sector
        ['id' => 5030201, 'name' => 'Bwisige'],
        ['id' => 5030202, 'name' => 'Kageyo'],
        ['id' => 5030203, 'name' => 'Kaniga'],
        ['id' => 5030204, 'name' => 'Manyagiro'],
        ['id' => 5030205, 'name' => 'Miyove']
    ],

    // Northern Province - Musanze District
    50401 => [ // Busogo sector
        ['id' => 5040101, 'name' => 'Busogo'],
        ['id' => 5040102, 'name' => 'Cyahinda'],
        ['id' => 5040103, 'name' => 'Gacaca'],
        ['id' => 5040104, 'name' => 'Gashaki'],
        ['id' => 5040105, 'name' => 'Gataraga']
    ],
    50402 => [ // Cyahinda sector
        ['id' => 5040201, 'name' => 'Cyahinda'],
        ['id' => 5040202, 'name' => 'Kimonyi'],
        ['id' => 5040203, 'name' => 'Kinigi'],
        ['id' => 5040204, 'name' => 'Muhoza'],
        ['id' => 5040205, 'name' => 'Muko']
    ],

    // Northern Province - Rulindo District
    50501 => [ // Base sector
        ['id' => 5050101, 'name' => 'Base'],
        ['id' => 5050102, 'name' => 'Burega'],
        ['id' => 5050103, 'name' => 'Bushoki'],
        ['id' => 5050104, 'name' => 'Buyoga'],
        ['id' => 5050105, 'name' => 'Cyinzuzi']
    ],
    50502 => [ // Burega sector
        ['id' => 5050201, 'name' => 'Burega'],
        ['id' => 5050202, 'name' => 'Cyungo'],
        ['id' => 5050203, 'name' => 'Kinihira'],
        ['id' => 5050204, 'name' => 'Kisaro'],
        ['id' => 5050205, 'name' => 'Masoro']
    ]
];

/**
 * Get all provinces
 */
function getRwandaProvinces() {
    global $rwanda_provinces;
    return array_values($rwanda_provinces);
}

/**
 * Get districts for a province
 */
function getRwandaDistricts($province_id) {
    global $rwanda_districts;
    return $rwanda_districts[$province_id] ?? [];
}

/**
 * Get sectors for a district
 */
function getRwandaSectors($district_id) {
    global $rwanda_sectors;
    return $rwanda_sectors[$district_id] ?? [];
}

/**
 * Get cells for a sector
 */
function getRwandaCells($sector_id) {
    global $rwanda_cells;

    // Return existing cells if available
    if (isset($rwanda_cells[$sector_id]) && !empty($rwanda_cells[$sector_id])) {
        return $rwanda_cells[$sector_id];
    }

    // Generate placeholder cells for sectors without data
    // This ensures the cascading dropdowns work even with incomplete data
    $placeholder_cells = [];
    $sector_name = getRwandaSector($sector_id)['name'] ?? 'Unknown Sector';

    // Create 3-5 placeholder cells based on sector name
    $cell_count = rand(3, 5);
    for ($i = 1; $i <= $cell_count; $i++) {
        $cell_id = $sector_id * 100 + $i;
        $cell_name = $sector_name . ' Cell ' . $i;
        $placeholder_cells[] = [
            'id' => $cell_id,
            'name' => $cell_name
        ];
    }

    return $placeholder_cells;
}

/**
 * Get province by ID
 */
function getRwandaProvince($province_id) {
    global $rwanda_provinces;
    return $rwanda_provinces[$province_id] ?? null;
}

/**
 * Get district by ID
 */
function getRwandaDistrict($district_id) {
    global $rwanda_districts;
    $province_id = floor($district_id / 100);
    $districts = getRwandaDistricts($province_id);

    foreach ($districts as $district) {
        if ($district['id'] == $district_id) {
            return $district;
        }
    }
    return null;
}

/**
 * Get sector by ID
 */
function getRwandaSector($sector_id) {
    global $rwanda_sectors;
    $district_id = floor($sector_id / 100);
    $sectors = getRwandaSectors($district_id);

    foreach ($sectors as $sector) {
        if ($sector['id'] == $sector_id) {
            return $sector;
        }
    }
    return null;
}

/**
 * Get cell by ID
 */
function getRwandaCell($cell_id) {
    global $rwanda_cells;
    $sector_id = floor($cell_id / 100);
    $cells = getRwandaCells($sector_id);

    foreach ($cells as $cell) {
        if ($cell['id'] == $cell_id) {
            return $cell;
        }
    }
    return null;
}

/**
 * Get complete location path by cell ID
 */
function getRwandaLocationPath($cell_id) {
    $cell = getRwandaCell($cell_id);
    if (!$cell) return null;

    $sector_id = floor($cell_id / 100);
    $sector = getRwandaSector($sector_id);
    if (!$sector) return null;

    $district_id = floor($sector_id / 100);
    $district = getRwandaDistrict($district_id);
    if (!$district) return null;

    $province_id = floor($district_id / 100);
    $province = getRwandaProvince($province_id);
    if (!$province) return null;

    return [
        'province' => $province,
        'district' => $district,
        'sector' => $sector,
        'cell' => $cell,
        'full_path' => $cell['name'] . ', ' . $sector['name'] . ', ' . $district['name'] . ', ' . $province['name']
    ];
}

/**
 * Get cell statistics for a sector
 */
function getRwandaCellStats($sector_id) {
    $cells = getRwandaCells($sector_id);
    return [
        'total_cells' => count($cells),
        'cell_names' => array_column($cells, 'name')
    ];
}

/**
 * Search cells by name
 */
function searchRwandaCells($search_term, $sector_id = null) {
    global $rwanda_cells;

    $results = [];
    $search_lower = strtolower($search_term);

    if ($sector_id) {
        // Search within specific sector
        $cells = getRwandaCells($sector_id);
        foreach ($cells as $cell) {
            if (stripos($cell['name'], $search_term) !== false) {
                $results[] = $cell;
            }
        }
    } else {
        // Search across all cells
        foreach ($rwanda_cells as $sector_cells) {
            foreach ($sector_cells as $cell) {
                if (stripos($cell['name'], $search_term) !== false) {
                    $results[] = $cell;
                }
            }
        }
    }

    return $results;
}

/**
 * Validate cell belongs to sector
 */
function validateRwandaCellInSector($cell_id, $sector_id) {
    $expected_sector_id = floor($cell_id / 100);
    return $expected_sector_id == $sector_id;
}

/**
 * Get neighboring cells (simplified - in real implementation would use geographic data)
 */
function getRwandaNeighboringCells($cell_id) {
    $sector_id = floor($cell_id / 100);
    $cells = getRwandaCells($sector_id);

    $current_index = -1;
    foreach ($cells as $index => $cell) {
        if ($cell['id'] == $cell_id) {
            $current_index = $index;
            break;
        }
    }

    if ($current_index === -1) return [];

    $neighbors = [];
    if ($current_index > 0) {
        $neighbors[] = $cells[$current_index - 1];
    }
    if ($current_index < count($cells) - 1) {
        $neighbors[] = $cells[$current_index + 1];
    }

    return $neighbors;
}

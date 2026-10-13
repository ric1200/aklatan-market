#!/usr/bin/env python3
# Developer utility (not part of the web upload): regenerates the starter
# catalogue INSERT statements and the original SVG placeholder covers.
import os, subprocess, sys, re

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

# slug, name, creator, category, price, compare, stock, isbn, publisher, format,
# language, pages, pubdate, short, long, flags(f,b,n,s)
P = [
("the-sampaguita-letters","The Sampaguita Letters","Marisol R. Villaflor",1,649,799,18,"9786214500117","Bagong Araw Publishing","Paperback","English",384,"2025-08-12",
 "A hundred unsent letters, one old wooden box, and the summer that changed a family forever.",
 "When Marisol Villaflor's grandmother dies, she leaves behind a tin of letters addressed to no one. Tracing each one across three provinces becomes an unexpectedly funny, tender search for the woman her grandmother really was. Winner of the 2025 Araw Fiction Prize, this edition includes a reading group guide.","FBS"),
("mango-season-at-barangay-siete","Mango Season at Barangay Siete","Danilo P. Cruz",1,529,None,24,"9786214500124","Bagong Araw Publishing","Paperback","English",312,"2025-02-19",
 "Sugar, gossip and a heatwave — a barrio novel that smells like ripe mangoes.",
 "The Delos Reyes family has held the last mango orchard in Barangay Siete for four generations. When a buyer arrives with a contract and a deadline, the whole neighbourhood picks a side. A warm, funny, deeply local novel about land, appetite and the ties that refuse to break.","B"),
("the-last-jeepney-ride","The Last Jeepney Ride","Elena Bautista",1,575,None,31,"9786214500131","Tala Press","Paperback","English",276,"2026-03-04",
 "A jeepney driver's final route becomes a portrait of the city at dusk.",
 "Told over one evening and eleven passengers, this slim, cinematic novel follows Mang Isko through the streets he has driven for thirty-one years. Each stop adds a passenger, a memory and a small reckoning. Quietly political, entirely human.","N"),
("salt-and-sunlight","Salt and Sunlight","Imelda Cortez",1,689,None,12,"9786214500148","Tala Press","Hardcover","English",420,"2025-11-08",
 "Two sisters, one fish port, and a reckoning forty years overdue.",
 "In a coastal town where the catch has been shrinking for a decade, Mila returns home to sell the family boat. Her sister has other plans. Imelda Cortez writes salt air and stubbornness with equal precision, and lands an ending that readers argue about.","S"),
("midnight-in-intramuros","Midnight in Intramuros","Rafael Santos",1,720,850,9,"9786214500155","Fort Santiago Books","Paperback","English",356,"2025-06-21",
 "A night guard, a stolen manuscript, and a walled city that keeps its own records.",
 "Every night at 2:00 AM, the stone walls of Intramuros seem to hold their breath. When a custodian finds a 19th-century manuscript missing from a locked cabinet, he has six hours to work out who took it and why. An atmospheric literary mystery.",""),
("a-quiet-house-in-baguio","A Quiet House in Baguio","Nadia Lim",1,560,None,3,"9786214500162","Highland Books","Paperback","English",298,"2024-12-01",
 "A grieving architect rents a cold house and finds it already occupied.",
 "Nadia Lim's restrained, luminous debut follows a woman rebuilding her life above the fog line. The house has opinions. So do the neighbours. A gentle novel about grief that never once asks for pity.",""),
("philippine-history-a-readable-survey","Philippine History: A Readable Survey","Dr. Aurora Mendoza",2,1250,None,14,"9786214500179","Katipunan Academic","Hardcover","English",512,"2025-05-30",
 "The standard college survey, rewritten for students who have two jobs and no time.",
 "Covers pre-colonial societies through the present in twenty-two chronological chapters, each with a timeline, key terms and source excerpts drawn from Philippine archives. Used widely in GE-PH courses and included in many senior high school reading lists.","S"),
("college-algebra-worktext","College Algebra Worktext (Revised Edition)","Prof. Benjamin A. Roque",2,890,None,22,"9786214500186","Katipunan Academic","Paperback","English",404,"2025-07-14",
 "Solved examples, worked drills and a full answer key for first-year algebra.",
 "Every chapter opens with a short diagnostic, moves through fully worked examples, and closes with graduated practice sets. The revised edition adds forty new word problems and a chapter on exponential models used in Philippine public health data.","B"),
("general-chemistry-laboratory-manual","General Chemistry Laboratory Manual","Dr. Carmela Ruiz",2,760,None,16,"9786214500193","Katipunan Academic","Spiral bound","English",268,"2025-04-02",
 "Twenty-eight safe, affordable laboratory experiments for large classes.",
 "Designed for schools where reagents are tight: every experiment lists a minimum-equipment variant and a substitute for hard-to-source chemicals. Includes pre-lab quizzes, data sheets and a troubleshooting table for each procedure.",""),
("accounting-for-small-businesses","Accounting for Small Businesses","Alicia Tan, CPA",2,980,None,11,"9786214500209","Taas Publishing","Paperback","English",372,"2025-09-25",
 "Bookkeeping, BIR compliance and cash flow for tiangge owners and freelancers.",
 "Written for the sari-sari store owner and the freelance designer alike, this practical guide covers the books you actually need, how to keep them, and what the Bureau of Internal Revenue expects. Includes a full sample set of records for one business year.",""),
("technical-writing-for-filipino-students","Technical Writing for Filipino Students","Prof. Noel Bautista",2,640,None,19,"9786214500216","Taas Publishing","Paperback","English",300,"2025-01-17",
 "Memoranda, reports, proposals and documentation — with Filipino workplace examples.",
 "A writing course in one volume. Each chapter pairs a short principle with annotated before-and-after samples taken from Philippine offices, NGOs and engineering firms, then asks the reader to revise a real document.",""),
("nursing-fundamentals-review","Nursing Fundamentals Review","Dr. Grace Villanueva",2,1480,1650,7,"9786214500223","San Lorenzo Medical Press","Paperback","English",640,"2025-10-10",
 "Nine hundred review questions with rationales for the Philippine Nurse Licensure Exam.",
 "Organised by the official PNLE blueprint and updated for the current competencies. Every answer carries a rationale plus a plain-language explanation of the underlying concept. Includes five full practice tests and a readiness tracker.",""),
("mga-tula-ng-dagat","Mga Tula ng Dagat","Liwayway Hernandez",3,420,None,26,"9786214500230","Bagong Araw Publishing","Paperback","Filipino",152,"2025-03-11",
 "Tatlumpu't anim na tula tungkol sa dagat, paglisan at pagbabalik.",
 "Sa unang koleksiyong ito, isinusulat ni Liwayway Hernandez ang dagat hindi bilang tanawin kundi bilang kamag-anak — maingay, matigas ang ulo, at hindi kailanman lubos na nakikilala. May mga tulang nasa Bikol at Tagalog.","B"),
("ang-bahay-na-may-tsinelas","Ang Bahay na May Tsinelas","Corazon dela Fuente",3,380,None,30,"9786214500247","Bagong Araw Publishing","Paperback","Filipino",188,"2025-06-06",
 "Isang maliit na nobela tungkol sa isang bahay na ayaw magpaalam.",
 "Sa loob ng apat na dekada, isang pamilya sa Bulacan ang pumasok at lumabas sa isang bahay na may kakaibang patakaran: hindi ka maaaring umalis nang walang tsinelas. Nakakatawa, malungkot, at tapat sa diwa ng mga tahanang Pilipino.","F"),
("florante-at-laura-annotated","Florante at Laura (Annotated Student Edition)","Francisco Balagtas",3,295,None,40,"9786214500254","Pamana Classics","Paperback","Filipino",214,"2024-06-15",
 "Ang klasiko, may talababa, glosaryo at gabay sa pagsusuri.",
 "Malinis na edisyon ng awit ni Balagtas na may modernong talababa sa bawat saknong, glosaryo ng lumang salita, at mga tanong sa pagsusuri para sa mga mag-aaral ng Filipino sa sekondarya.","B"),
("noli-me-tangere-student-edition","Noli Me Tangere (Student Edition)","Jose Rizal",3,350,None,35,"9786214500261","Pamana Classics","Paperback","English",468,"2024-06-15",
 "The Charles Derbyshire translation with margin notes and a student glossary.",
 "A clean, affordable student printing with chapter summaries in the margins, a glossary of Spanish-era terms, and a short essay on the novel's publication history. Widely used in Grade 9 Filipino and English classes.",""),
("dagat-sa-silangan-mga-kuwentong-bayan","Dagat sa Silangan: Mga Kuwentong Bayan","Teresa Manalo",3,465,None,21,"9786214500278","Pamana Classics","Paperback","Filipino",236,"2025-08-01",
 "Labindalawang kuwentong bayan mula sa mga baybaying lalawigan, muling isinalaysay.",
 "Inipon at muling isinalaysay ni Teresa Manalo ang labindalawang kuwento mula sa mga lalawigan ng Quezon, Samar at Surigao. May tala ang bawat kuwento tungkol sa pinagmulan nito at sa mga taong nagbahagi nito.","N"),
("ang-mangagawa-mga-sanaysay","Ang Mangagawa: Mga Sanaysay sa Hanapbuhay","Joel Marasigan",3,510,None,13,"9786214500285","Tala Press","Paperback","Filipino",264,"2025-05-20",
 "Labing-anim na sanaysay tungkol sa paggawa, sahod at karangalan.",
 "Mula sa pabrika ng sapatos sa Marikina hanggang sa mga cloud kitchen ng Metro Manila, sinusuri ni Joel Marasigan ang mga trabahong madalas hindi napapansin. Personal at mapagmasid, hindi mabigat sa teorya.","S"),
("kadena-alagad-ng-buwan-vol-1","Kadena: Alagad ng Buwan Vol. 1","Kiko Yabut",4,399,None,44,"9786214500292","Halo Komiks","Paperback","Filipino",192,"2025-09-01",
 "Isang binatang may tanikalang pilak, isang lungsod na puno ng anino.",
 "Nagsimula bilang webcomic na may milyong panonood, ngayon nasa print na. Si Kiko Yabut ay gumuhit ng lungsod na bahagyang Maynila, bahagyang panaginip, at isang bida na hindi sigurado kung siya ang bayani.","B"),
("kadena-alagad-ng-buwan-vol-2","Kadena: Alagad ng Buwan Vol. 2","Kiko Yabut",4,399,None,38,"9786214500308","Halo Komiks","Paperback","Filipino",200,"2026-01-20",
 "Nagtuloy ang laban sa ikalawang tomo — may bagong kalaban at lumang kasalanan.",
 "Sa ikalawang tomo, kailangan nang harapin ng bida ang pinagmulan ng tanikalang pilak. Dalawampung bagong kabanata, kasama ang isang espesyal na kuwento tungkol sa unang Alagad ng Buwan.","N"),
("barako-boys-vol-1","Barako Boys Vol. 1","Ysa Ferrer",4,385,None,27,"9786214500315","Halo Komiks","Paperback","Filipino",176,"2025-07-07",
 "Tatlong magkakaibigan, isang basketball court, at isang napakalaking pustahan.",
 "Isang nakakatawang serye tungkol sa pagkakaibigan at lokal na basketball. Ang unang tomo ay may kasamang sketchbook section at komentaryo ng may-akda sa bawat kabanata.",""),
("the-cram-school-club-vol-1","The Cram School Club Vol. 1","Hana Mizuki",4,450,None,33,"9786214500322","Shinbun Comics PH","Paperback","English",208,"2026-02-14",
 "Five students, one review centre, and a study group that refuses to fail.",
 "A warm school comedy about a tutoring centre where the tutors are barely older than the students. First volume of the series, with a bonus chapter set during enrolment week.","N"),
("star-fisher-chronicles-vol-3","Star Fisher Chronicles Vol. 3","Hana Mizuki",4,455,None,20,"9786214500339","Shinbun Comics PH","Paperback","English",216,"2025-12-05",
 "The fleet reaches the quiet sea, and the crew finally learns what they are carrying.",
 "Volume three raises the stakes: a mutiny, a broken compass, and a chapter told entirely from the ship's point of view. Collects chapters 18 to 27.",""),
("pinoy-superheroes-anthology","Pinoy Superheroes: An Anthology","Various Artists",4,620,None,15,"9786214500346","Halo Komiks","Hardcover","English",288,"2025-11-22",
 "Eighteen creators reinvent the Filipino superhero in eighteen short comics.",
 "An oversized hardcover anthology pairing established komiks artists with newer voices. Each story is introduced by its creator, and the volume closes with a gallery of character designs.","S"),
("ang-maliit-na-banga","Ang Maliit na Banga","Lourdes Aquino",5,320,None,42,"9786214500353","Batang Aklat","Paperback","Filipino",32,"2025-04-18",
 "Isang batang banga na natutong mag-ingat sa sarili.",
 "Ang maliit na banga ay ayaw mahulog. Sa tulong ng lola at ng kanyang hardin, natutunan niyang ang pag-iingat ay hindi takot kundi pagmamahal. Mainam para sa edad 3 hanggang 7.","B"),
("maya-and-the-mango-tree","Maya and the Mango Tree","Cindy Reyes",5,340,None,37,"9786214500360","Batang Aklat","Paperback","English",36,"2025-09-12",
 "A girl, a tree, and a very patient carabao.",
 "Maya is sure the mango tree in her lola's yard is hiding something. It takes one rainy season, three arguments and a carabao named Bino to find out what. A gentle early-reader story about patience and harvest.",""),
("ang-buwaya-na-ayaw-maligo","Ang Buwaya na Ayaw Maligo","Pia Gonzales",5,295,None,29,"9786214500377","Batang Aklat","Paperback","Filipino",28,"2025-02-08",
 "Ang buwayang ayaw maligo ay may isang napakalaking problema sa ilog.",
 "Nakakatawang kuwento tungkol sa kalinisan at pagkakaibigan. May malalaking letra at paulit-ulit na berso para sa mga nagsisimula pa lamang magbasa.",""),
("laro-tayo-tagalog-activity-book","Laro Tayo! Tagalog Activity Book","Studio Araw",5,260,None,50,"9786214500384","Batang Aklat","Paperback","Filipino",64,"2026-03-18",
 "Puzzles, mazes and word games that build Tagalog vocabulary.",
 "Sixty-four pages of screen-free activities: salita searches, pattern games, colouring pages and simple crossword puzzles. Answer key at the back. Recommended for ages 5 to 9.","N"),
("the-brave-little-bangka","The Brave Little Bangka","Miguel Ferrer",5,330,None,23,"9786214500391","Batang Aklat","Hardcover","English",40,"2025-06-30",
 "A small outrigger boat proves that brave does not have to mean big.",
 "When a storm scatters the fishing fleet, the smallest boat in the harbour is the one still afloat. A reassuring bedtime story with luminous illustrations.",""),
("filipino-food-monthly-issue-118","Filipino Food Monthly — Issue 118","FFM Editorial",6,180,None,60,"9770118001184","Filipino Food Monthly","Magazine","English",96,"2026-02-01",
 "The heirloom rice issue: five provinces, eleven varieties, one very long table.",
 "This month: heirloom rice from Ifugao to Antique, a guide to cooking with kalamansi peel, and a recipe diary from a family that has run the same carinderia since 1974.","N"),
("travel-philippines-quarterly-islands","Travel Philippines Quarterly — Islands Edition","TPQ Editorial",6,220,None,34,"9770118002198","Travel Philippines Quarterly","Magazine","English",132,"2025-12-15",
 "Twelve islands without an airport, mapped and photographed.",
 "A quarterly devoted to slow travel. This edition profiles twelve islands reachable only by boat, with practical sailing schedules, guesthouse rates and a foldout regional map.",""),
("comics-review-annual-2026","Comics Review Annual 2026","Comics Review",6,350,None,25,"9770118003204","Comics Review","Magazine","English",168,"2026-01-05",
 "The year in Philippine comics — reviews, interviews and the best-of list.",
 "Annual roundup with forty reviews, six long-form interviews and the editorial team's top fifty releases of the previous year. Includes a directory of komiks creators and presses.",""),
("home-and-harvest-magazine-42","Home & Harvest Magazine No. 42","HH Editorial",6,195,None,31,"9770118004211","Home & Harvest","Magazine","English",104,"2025-10-22",
 "Small-space gardening for city balconies and compound walls.",
 "Practical guides for growing talbos ng kamote, kangkong and herbs in containers, plus a feature on building a rain barrel for under ₱1,500.",""),
("himig-kundiman-limited-vinyl","Himig Kundiman (Limited Vinyl LP)","Various Artists",7,1899,2200,8,"","Isla Records","Vinyl LP","Filipino",0,"2025-11-14",
 "Twenty kundiman classics, remastered and pressed on 180-gram vinyl.",
 "A carefully sequenced anthology of kundiman standards from 1935 to 1962, restored from the original acetates and pressed on heavyweight vinyl. Gatefold sleeve with bilingual liner notes and full lyric transcriptions.","F"),
("opm-90s-greatest-hits-2cd","OPM 90s Greatest Hits (2-CD)","Various Artists",7,620,None,19,"","Isla Records","2× Compact Disc","English",0,"2025-08-08",
 "Thirty-six tracks that defined a decade of Filipino radio.",
 "Two discs of the songs that played in every jeepney, canteen and school program of the 1990s, remastered for the first time. Includes a twenty-page booklet of band photographs.",""),
("tala-acoustic-sessions-cd","Tala Acoustic Sessions (CD)","Tala",7,550,None,26,"","Bughaw Music","Compact Disc","English",0,"2026-01-30",
 "Nine songs recorded live in a Quezon City living room, one take each.",
 "Stripped back arrangements, a borrowed upright piano and no overdubs. The album that convinced a lot of people that Tala sounds better without a band.","N"),
("pagsibol-vinyl-ep","Pagsibol (Vinyl EP)","Bughaw",7,980,None,10,"","Bughaw Music","Vinyl EP","Filipino",0,"2025-05-16",
 "Four songs about growing things, pressed on translucent green vinyl.",
 "A four-track EP recorded over one rainy weekend in Laguna. Limited pressing of five hundred numbered copies, each with a seed-paper insert.","S"),
("sa-ilalim-ng-tulay-restored-dvd","Sa Ilalim ng Tulay (Restored Classic DVD)","Director: R. Villamor",8,450,None,18,"","Sineng Pamana","DVD","Filipino",0,"2025-06-12",
 "The 1976 classic, restored frame by frame from the only surviving print.",
 "Long considered lost, this landmark drama was restored from a damaged 16mm print over two years. The disc includes a making-of documentary, an interview with the cinematographer, and English subtitles.","B"),
("manila-film-archive-collection-dvd","Manila Film Archive Collection (3-DVD Box)","Manila Film Archive",8,1150,None,9,"","Sineng Pamana","3× DVD Box Set","Filipino",0,"2025-10-01",
 "Nine short films from 1952 to 1989, in one restored box set.",
 "A three-disc set covering documentary, animation and experimental film from Philippine archives, with scholarly commentary tracks and a forty-page booklet.",""),
("the-sari-sari-store-short-films-dvd","The Sari-Sari Store: Short Films (DVD)","Various Directors",8,390,None,22,"","Sineng Pamana","DVD","Filipino",0,"2026-02-20",
 "Seven short films, one corner store, seven different neighbourhoods.",
 "A collection of award-winning shorts from the last five years, each set in or around a neighbourhood sari-sari store. Total running time 96 minutes, with director commentaries.","N"),
("hand-bound-katipunan-notebook","Hand-bound Katipunan Notebook","Aklatan Studio",9,480,None,28,"","Aklatan Studio","Notebook","English",160,"2025-03-01",
 "160 pages of cream paper, hand-bound with a linen thread spine.",
 "Made for people who still write their reading notes by hand. Bound in Quezon City with a linen thread spine that opens flat, a ribbon marker and 160 pages of 90gsm cream paper.","S"),
("vintage-manuscript-bookmarks","Vintage Manuscript Bookmarks (Set of 6)","Aklatan Studio",9,220,None,64,"","Aklatan Studio","Set of 6","English",0,"2025-07-19",
 "Six printed bookmarks reproducing pages from century-old Philippine manuscripts.",
 "Each bookmark reproduces a different manuscript page from the national archives, printed on heavy card with a matte finish and a tassel in one of six colours.",""),
("brass-bookend-pair-reading-carabao","Brass Bookend Pair: Reading Carabao","Aklatan Studio",9,1350,None,6,"","Aklatan Studio","Bookends","English",0,"2025-09-09",
 "Solid brass bookends shaped like a carabao that has discovered literature.",
 "Cast in brass, hand-polished and felted underneath so they will not scratch a shelf. Each pair weighs 1.9 kg and is packed in a cloth bag. A genuinely permanent gift for a reader.","S"),
("typewriter-key-keychains","Typewriter Key Keychains","Aklatan Studio",9,150,None,80,"","Aklatan Studio","Keychain","English",0,"2025-04-25",
 "Genuine typewriter keys, cleaned and mounted on a leather loop.",
 "Keys are salvaged from decommissioned Philippine office typewriters, so every keychain is a little different. Choose from A to Z and a few punctuation marks at checkout notes.",""),
("filipino-literature-starter-bundle","Filipino Literature Starter Bundle","Aklatan Market Curation",10,1490,1750,12,"","Aklatan Market","Bundle of 4","English",0,"2025-12-01",
 "Four Filipino novels and story collections chosen as a first shelf.",
 "Includes The Sampaguita Letters, Mango Season at Barangay Siete, Mga Tula ng Dagat and Ang Bahay na May Tsinelas. Bundled at a lower price than buying the four separately, with a printed reading guide.","FBS"),
("student-survival-book-bundle","Student Survival Book Bundle","Aklatan Market Curation",10,2150,2480,10,"","Aklatan Market","Bundle of 4","English",0,"2025-12-01",
 "The four titles students ask for most, at one friendlier price.",
 "Includes Philippine History: A Readable Survey, College Algebra Worktext, Technical Writing for Filipino Students and Accounting for Small Businesses. Ideal for first-year college students.","B"),
("kids-storytime-bundle","Kids' Storytime Bundle (5 books)","Aklatan Market Curation",10,1395,1600,16,"","Aklatan Market","Bundle of 5","English",0,"2026-01-15",
 "Five picture books for bedtime, in English and Filipino.",
 "Includes Ang Maliit na Banga, Maya and the Mango Tree, Ang Buwaya na Ayaw Maligo, The Brave Little Bangka and Laro Tayo! Tagalog Activity Book. Gift-wrapped on request.","N"),
]

CAT_PALETTE = {
    1: ("#172B4D", "#F7F3E9", "#E5A93D", "Fiction & Novels"),
    2: ("#2C6E63", "#F2FBF8", "#E5A93D", "Academic & Reference"),
    3: ("#E0AC45", "#1B2340", "#172B4D", "Filipino Books"),
    4: ("#9C4A34", "#FBEFE7", "#E5A93D", "Manga & Comics"),
    5: ("#7C9885", "#12231C", "#123A2B", "Children's Books"),
    6: ("#4E4468", "#F6F2FB", "#E5A93D", "Magazines"),
    7: ("#14555C", "#ECF8F9", "#E5A93D", "Music"),
    8: ("#5B3A29", "#FAF1E8", "#E5A93D", "Movies & DVDs"),
    9: ("#B97F2A", "#241708", "#172B4D", "Stationery"),
    10: ("#274A78", "#F1F6FD", "#E5A93D", "Book Bundles"),
}

def sql_str(v):
    if v is None or v == "":
        return "NULL"
    return "'" + str(v).replace("\\", "\\\\").replace("'", "''") + "'"

def esc_xml(s):
    return (s.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;"))

def wrap(text, max_chars):
    words = text.split()
    lines, line = [], ""
    for w in words:
        if line and len(line) + 1 + len(w) > max_chars:
            lines.append(line); line = w
        else:
            line = (line + " " + w).strip()
    if line:
        lines.append(line)
    return lines

def make_cover(slug, title, creator, cat, kind_label):
    bg, fg, rule, catname = CAT_PALETTE[cat]
    lines = wrap(title, 22)[:5]
    while len(lines) < 2:
        lines.append("")
    font = 40 if len(lines) <= 3 else (34 if len(lines) == 4 else 29)
    start_y = 300 - (len(lines) - 1) * (font * 0.62) / 2 + 8
    tspans = "".join(
        '<tspan x="196" dy="%s">%s</tspan>' % ("0" if i == 0 else "%.1f" % (font * 1.16), esc_xml(l))
        for i, l in enumerate(lines))
    creator_line = esc_xml(creator)
    if len(creator_line) > 34:
        creator_line = creator_line[:33] + "…"
    label = esc_xml(catname.upper())
    svg = f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 600" width="400" height="600" role="img" aria-label="{esc_xml(title)} cover">
  <rect width="400" height="600" fill="{bg}"/>
  <rect x="0" y="0" width="16" height="600" fill="rgba(0,0,0,0.20)"/>
  <rect x="16" y="0" width="3" height="600" fill="rgba(255,255,255,0.13)"/>
  <rect x="42" y="34" width="322" height="532" fill="none" stroke="{rule}" stroke-opacity="0.55" stroke-width="1.4"/>
  <g opacity="0.35" stroke="{rule}" stroke-width="1" fill="none">
    <path d="M42 96h322"/>
  </g>
  <text x="196" y="76" text-anchor="middle" font-family="'Inter',Helvetica,Arial,sans-serif" font-size="12.5"
        letter-spacing="3.2" font-weight="600" fill="{rule}">{label}</text>
  <text x="196" y="{start_y}" text-anchor="middle" font-family="Georgia,'Times New Roman',serif"
        font-size="{font}" font-weight="700" fill="{fg}" letter-spacing="-0.4">{tspans}</text>
  <g opacity="0.5" stroke="{fg}" stroke-width="1.2" fill="none">
    <path d="M170 468h52"/>
  </g>
  <text x="196" y="500" text-anchor="middle" font-family="'Inter',Helvetica,Arial,sans-serif" font-size="14"
        fill="{fg}" opacity="0.92">{creator_line}</text>
  <text x="196" y="528" text-anchor="middle" font-family="'Inter',Helvetica,Arial,sans-serif" font-size="10.5"
        letter-spacing="1.8" fill="{rule}" opacity="0.95">{esc_xml(kind_label.upper())}</text>
  <g transform="translate(184,546) scale(1.3)" opacity="0.75" fill="none" stroke="{fg}" stroke-width="1.6"
     stroke-linejoin="round">
    <path d="M12 6.5C10.4 5 8.4 4.2 6.2 4.2H3.4v14.2h2.8c2.2 0 4.2.8 5.8 2.3 1.6-1.5 3.6-2.3 5.8-2.3h2.8V4.2h-2.8c-2.2 0-4.2.8-5.8 2.3Z"/>
    <path d="M12 6.5v14.2"/>
  </g>
</svg>
'''
    return svg

def main():
    rows = []
    covers_dir = os.path.join(ROOT, "assets", "images", "covers")
    os.makedirs(covers_dir, exist_ok=True)
    counters = {}
    for i, item in enumerate(P, start=1):
        (slug, name, creator, cat, price, compare, stock, isbn, publisher, fmt, lang,
         pages, pubdate, short, long_, flags) = item
        counters[cat] = counters.get(cat, 0) + 1
        sku = "AKM-%02d-%03d" % (cat, counters[cat])
        f, b, n, s = ('F' in flags), ('B' in flags), ('N' in flags), ('S' in flags)
        image = "assets/images/covers/%s.svg" % slug
        kind = {"Book Bundles": "Bundle", "Music": "Audio", "Movies & DVDs": "Video",
                "Magazines": "Magazine", "Stationery": "Accessory"}.get(CAT_PALETTE[cat][3], "Book")
        with open(os.path.join(covers_dir, slug + ".svg"), "w", encoding="utf-8") as fh:
            fh.write(make_cover(slug, name, creator, cat, kind))
        values = [
            str(cat), sql_str(name), sql_str(slug), sql_str(sku), sql_str(creator),
            sql_str(isbn), sql_str(publisher), sql_str(fmt), sql_str(lang),
            "NULL" if not pages else str(int(pages)), sql_str(pubdate),
            sql_str(short), sql_str(long_), "%.2f" % price,
            ("%.2f" % compare) if compare else "NULL", str(int(stock)), "1",
            "1" if f else "0", "1" if b else "0", "1" if n else "0", "1" if s else "0",
        ]
        assert len(values) == 21, len(values)
        rows.append("(" + ",".join(values) + ")")

    sql = ("-- -----------------------------------------------------------------------------\n"
           "-- products : starter catalogue (every cover image is a local, original SVG)\n"
           "-- -----------------------------------------------------------------------------\n"
           "INSERT INTO `products`\n"
           "  (`category_id`,`name`,`slug`,`sku`,`creator`,`isbn`,`publisher`,`product_format`,`language`,`pages`,\n"
           "   `publication_date`,`short_description`,`description`,`price`,`compare_at_price`,`stock_quantity`,\n"
           "   `is_active`,`is_featured`,`is_bestseller`,`is_new_arrival`,`is_staff_pick`) VALUES\n"
           + ",\n".join(rows) + ";\n")

    demo_path = os.path.join(ROOT, "tools", "demo_data.sql")
    with open(demo_path, encoding="utf-8") as fh:
        demo = fh.read()

    path = os.path.join(ROOT, "database", "aklatan_market.sql")
    with open(path, encoding="utf-8") as fh:
        content = fh.read()
    content = content.replace("-- @@PRODUCTS@@", sql).replace("-- @@DEMO@@", demo)
    with open(path, "w", encoding="utf-8") as fh:
        fh.write(content)
    print("products:", len(rows), "covers written:", len(os.listdir(covers_dir)))

if __name__ == "__main__":
    main()

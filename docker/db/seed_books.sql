-- Seed: aggiunge almeno 5 libri per ogni genere
-- Generi carenti: poesia(0), thriller(1), cucina(1), horror(1), viaggio(1), manga(1), giallo(1), self-help(1), fumetto(1), dystopia(2), scienza(2), biografia(3), saggistica(3), fantasy(3), storico(3), young-adult(4), romanzo(4), fantascienza(4)

SET @user_id = 1;

-- ===== POESIA (0 libri) =====
INSERT INTO book (title, book_author, description, isbn, page_count, publisher, publication_year, created_at, added_by_id) VALUES
('Foglie d''erba', 'Walt Whitman', 'La raccolta poetica piÃ¹ influente della letteratura americana, un inno alla natura, alla democrazia e alla vita.', '9788806219451', 560, 'Einaudi', 1855, NOW(), @user_id),
('I fiori del male', 'Charles Baudelaire', 'Raccolta di poesie che esplora il conflitto tra bellezza e decadenza, tra idealismo e disperazione nella Parigi del XIX secolo.', '9788807900662', 320, 'Feltrinelli', 1857, NOW(), @user_id),
('Canto generale', 'Pablo Neruda', 'Un poema epico che abbraccia la storia e la natura dell''America Latina, dalla preistoria al XX secolo.', '9788845907449', 480, 'Adelphi', 1950, NOW(), @user_id),
('Ossi di seppia', 'Eugenio Montale', 'La prima raccolta poetica di Montale, simbolo dell''ermetismo italiano, dove il paesaggio ligure diventa metafora esistenziale.', '9788804668060', 180, 'Mondadori', 1925, NOW(), @user_id),
('Poesie', 'Emily Dickinson', 'L''opera completa della poetessa americana, con versi brevi e intensi che esplorano morte, immortalitÃ  e natura.', '9788807900945', 640, 'Feltrinelli', 1890, NOW(), @user_id),
('L''infinito e altre poesie', 'Giacomo Leopardi', 'Le piÃ¹ celebri liriche di Leopardi, dai Canti ai Pensieri, espressione del pessimismo cosmico.', '9788806173524', 256, 'Einaudi', 1835, NOW(), @user_id);

-- Associa generi per Poesia
INSERT INTO book_genre (book_id, genre_id) SELECT b.id, 31 FROM book b WHERE b.isbn IN ('9788806219451','9788807900662','9788845907449','9788804668060','9788807900945','9788806173524');
-- Leopardi e Montale anche Classici
INSERT INTO book_genre (book_id, genre_id) SELECT b.id, 36 FROM book b WHERE b.isbn IN ('9788804668060','9788806173524');

-- ===== THRILLER (1 libro) =====
INSERT INTO book (title, book_author, description, isbn, page_count, publisher, publication_year, created_at, added_by_id) VALUES
('Il silenzio degli innocenti', 'Thomas Harris', 'L''agente FBI Clarice Starling chiede aiuto al geniale serial killer Hannibal Lecter per catturare un altro assassino.', '9788804667735', 432, 'Mondadori', 1988, NOW(), @user_id),
('La ragazza del treno', 'Paula Hawkins', 'Rachel osserva ogni giorno una coppia perfetta dal finestrino del treno. Poi un giorno vede qualcosa di scioccante.', '9788868369507', 352, 'Piemme', 2015, NOW(), @user_id),
('Il codice Da Vinci', 'Dan Brown', 'Il professor Langdon indaga su un omicidio al Louvre che lo porta a scoprire un segreto millenario custodito da una societÃ  segreta.', '9788804667599', 480, 'Mondadori', 2003, NOW(), @user_id),
('Gone Girl - L''amore bugiardo', 'Gillian Flynn', 'Il giorno del quinto anniversario di matrimonio, Amy scompare. Il marito Nick diventa il principale sospettato.', '9788817080811', 448, 'Rizzoli', 2012, NOW(), @user_id),
('Io uccido', 'Giorgio Faletti', 'Un serial killer terrorizza Montecarlo con messaggi in diretta radiofonica. Un thriller che ha segnato la narrativa italiana.', '9788860618658', 640, 'Baldini+Castoldi', 2002, NOW(), @user_id);

INSERT INTO book_genre (book_id, genre_id) SELECT b.id, 24 FROM book b WHERE b.isbn IN ('9788804667735','9788868369507','9788804667599','9788817080811','9788860618658');
-- Il codice Da Vinci anche Avventura
INSERT INTO book_genre (book_id, genre_id) SELECT b.id, 27 FROM book b WHERE b.isbn = '9788804667599';

-- ===== CUCINA (1 libro) =====
INSERT INTO book (title, book_author, description, isbn, page_count, publisher, publication_year, created_at, added_by_id) VALUES
('La scienza della pasticceria', 'Dario Bressanini', 'La chimica e la fisica dietro dolci perfetti: dalle meringhe ai biscotti, spiegati con rigore scientifico.', '9788858015803', 256, 'Gribaudo', 2014, NOW(), @user_id),
('Sale, grasso, acido, calore', 'Samin Nosrat', 'I quattro elementi fondamentali della cucina, spiegati con semplicitÃ  per trasformare chiunque in un cuoco migliore.', '9788804716853', 480, 'Mondadori', 2017, NOW(), @user_id),
('Il cucchiaio d''argento', 'AA.VV.', 'La bibbia della cucina italiana con oltre 2000 ricette della tradizione, dal primo piatto al dolce.', '9788891800176', 1500, 'Editoriale Domus', 1950, NOW(), @user_id),
('Kitchen Confidential', 'Anthony Bourdain', 'Le avventure di uno chef nelle cucine di New York: un racconto crudo e ironico del mondo della ristorazione.', '9788807884399', 320, 'Feltrinelli', 2000, NOW(), @user_id),
('Fatto in casa da Benedetta', 'Benedetta Rossi', 'Ricette facili e veloci per tutti i giorni, dalla colazione alla cena, con ingredienti semplici.', '9788804707318', 288, 'Mondadori', 2017, NOW(), @user_id);

INSERT INTO book_genre (book_id, genre_id) SELECT b.id, 38 FROM book b WHERE b.isbn IN ('9788858015803','9788804716853','9788891800176','9788807884399','9788804707318');
-- Bressanini anche Scienza
INSERT INTO book_genre (book_id, genre_id) SELECT b.id, 40 FROM book b WHERE b.isbn = '9788858015803';

-- ===== HORROR (1 libro) =====
INSERT INTO book (title, book_author, description, isbn, page_count, publisher, publication_year, created_at, added_by_id) VALUES
('Shining', 'Stephen King', 'Jack Torrance accetta il lavoro di custode invernale all''Overlook Hotel, ignaro che l''edificio nasconde forze oscure.', '9788868368234', 512, 'Sperling & Kupfer', 1977, NOW(), @user_id),
('Dracula', 'Bram Stoker', 'Il conte Dracula lascia la Transilvania per l''Inghilterra, dove un gruppo di coraggiosi tenta di fermarlo.', '9788807900211', 480, 'Feltrinelli', 1897, NOW(), @user_id),
('L''esorcista', 'William Peter Blatty', 'Una madre disperata chiede aiuto a due sacerdoti quando la figlia dodicenne mostra segni di possessione demoniaca.', '9788804667926', 400, 'Mondadori', 1971, NOW(), @user_id),
('Frankenstein', 'Mary Shelley', 'Lo scienziato Victor Frankenstein crea un essere vivente, ma la creatura, rifiutata dal mondo, si trasforma in un mostro vendicativo.', '9788807900303', 288, 'Feltrinelli', 1818, NOW(), @user_id),
('Il richiamo di Cthulhu', 'H.P. Lovecraft', 'Racconti dell''orrore cosmico: divinitÃ  ancestrali dormono negli abissi, e il loro risveglio significherebbe la fine dell''umanitÃ .', '9788804668732', 320, 'Mondadori', 1928, NOW(), @user_id);

INSERT INTO book_genre (book_id, genre_id) SELECT b.id, 25 FROM book b WHERE b.isbn IN ('9788868368234','9788807900211','9788804667926','9788807900303','9788804668732');
-- Dracula, Frankenstein anche Classici
INSERT INTO book_genre (book_id, genre_id) SELECT b.id, 36 FROM book b WHERE b.isbn IN ('9788807900211','9788807900303');

-- ===== VIAGGIO (1 libro) =====
INSERT INTO book (title, book_author, description, isbn, page_count, publisher, publication_year, created_at, added_by_id) VALUES
('In Patagonia', 'Bruce Chatwin', 'Un viaggio letterario attraverso la Patagonia, tra personaggi eccentrici, paesaggi sconfinati e storie dimenticate.', '9788845907562', 240, 'Adelphi', 1977, NOW(), @user_id),
('Sulla strada', 'Jack Kerouac', 'Il viaggio on the road di Sal Paradise e Dean Moriarty attraverso l''America, manifesto della Beat Generation.', '9788804668398', 320, 'Mondadori', 1957, NOW(), @user_id),
('Il giro del mondo in 80 giorni', 'Jules Verne', 'Phileas Fogg scommette che riuscirÃ  a circumnavigare il globo in soli ottanta giorni, affrontando mille avventure.', '9788807900501', 288, 'Feltrinelli', 1873, NOW(), @user_id),
('Mangia prega ama', 'Elizabeth Gilbert', 'Dopo un divorzio doloroso, l''autrice intraprende un viaggio in Italia, India e Indonesia alla ricerca di sÃ© stessa.', '9788817038645', 384, 'Rizzoli', 2006, NOW(), @user_id),
('Vagabonding', 'Rolf Potts', 'Una guida filosofica e pratica al viaggio a lungo termine, per chi vuole esplorare il mondo con calma e consapevolezza.', '9780812992182', 224, 'Villard', 2002, NOW(), @user_id);

INSERT INTO book_genre (book_id, genre_id) SELECT b.id, 39 FROM book b WHERE b.isbn IN ('9788845907562','9788804668398','9788807900501','9788817038645','9780812992182');
-- Kerouac anche Classici, Verne anche Avventura+Classici
INSERT INTO book_genre (book_id, genre_id) SELECT b.id, 36 FROM book b WHERE b.isbn IN ('9788804668398','9788807900501');
INSERT INTO book_genre (book_id, genre_id) SELECT b.id, 27 FROM book b WHERE b.isbn = '9788807900501';

-- ===== MANGA (1 libro) =====
INSERT INTO book (title, book_author, description, isbn, page_count, publisher, publication_year, created_at, added_by_id) VALUES
('One Piece Vol. 1', 'Eiichiro Oda', 'Monkey D. Luffy, un ragazzo il cui corpo Ã¨ diventato di gomma, parte per trovare il leggendario tesoro One Piece e diventare il Re dei Pirati.', '9788828730200', 200, 'Star Comics', 1997, NOW(), @user_id),
('Dragon Ball Vol. 1', 'Akira Toriyama', 'Le avventure del giovane Goku alla ricerca delle sette sfere del drago che esaudiscono qualsiasi desiderio.', '9788828730309', 192, 'Star Comics', 1984, NOW(), @user_id),
('Attack on Titan Vol. 1', 'Hajime Isayama', 'L''umanitÃ  vive rinchiusa in cittÃ  fortificate per proteggersi dai Titani, giganti divoratori di uomini.', '9788828730408', 208, 'Planet Manga', 2009, NOW(), @user_id),
('Death Note Vol. 1', 'Tsugumi Ohba', 'Lo studente Light Yagami trova un quaderno soprannaturale: chiunque il cui nome venga scritto nel quaderno muore.', '9788828730507', 200, 'Planet Manga', 2003, NOW(), @user_id),
('Fullmetal Alchemist Vol. 1', 'Hiromu Arakawa', 'I fratelli Elric usano l''alchimia per tentare di riportare in vita la madre, ma il rituale va terribilmente storto.', '9788828730606', 192, 'Planet Manga', 2001, NOW(), @user_id);

INSERT INTO book_genre (book_id, genre_id) SELECT b.id, 32 FROM book b WHERE b.isbn IN ('9788828730200','9788828730309','9788828730408','9788828730507','9788828730606');
-- Manga anche Avventura
INSERT INTO book_genre (book_id, genre_id) SELECT b.id, 27 FROM book b WHERE b.isbn IN ('9788828730200','9788828730309','9788828730408');
-- Death Note anche Thriller
INSERT INTO book_genre (book_id, genre_id) SELECT b.id, 24 FROM book b WHERE b.isbn = '9788828730507';

-- ===== GIALLO (1 libro) =====
INSERT INTO book (title, book_author, description, isbn, page_count, publisher, publication_year, created_at, added_by_id) VALUES
('Assassinio sull''Orient Express', 'Agatha Christie', 'Il detective Hercule Poirot indaga su un omicidio a bordo del lussuoso Orient Express bloccato dalla neve.', '9788804668183', 256, 'Mondadori', 1934, NOW(), @user_id),
('Il commissario Montalbano - La forma dell''acqua', 'Andrea Camilleri', 'Il primo caso del commissario Montalbano: un cadavere trovato in una zona malfamata di Vigata.', '9788838936302', 180, 'Sellerio', 1994, NOW(), @user_id),
('Il mastino dei Baskerville', 'Arthur Conan Doyle', 'Sherlock Holmes indaga sulla leggenda di un mastino demoniaco che perseguita la famiglia Baskerville nelle brughiere del Devon.', '9788807900174', 224, 'Feltrinelli', 1902, NOW(), @user_id),
('La veritÃ  sul caso Harry Quebert', 'JoÃ«l Dicker', 'Lo scrittore Marcus Goldman indaga sulla scomparsa di una ragazza 33 anni prima, collegata al suo mentore Harry Quebert.', '9788845293191', 672, 'Bompiani', 2012, NOW(), @user_id),
('Il profumo', 'Patrick SÃ¼skind', 'Nella Parigi del XVIII secolo, Jean-Baptiste Grenouille possiede un olfatto sovrumano e un''ossessione mortale per i profumi.', '9788830400900', 304, 'Longanesi', 1985, NOW(), @user_id);

INSERT INTO book_genre (book_id, genre_id) SELECT b.id, 23 FROM book b WHERE b.isbn IN ('9788804668183','9788838936302','9788807900174','9788845293191','9788830400900');
-- Christie e Doyle anche Classici
INSERT INTO book_genre (book_id, genre_id) SELECT b.id, 36 FROM book b WHERE b.isbn IN ('9788804668183','9788807900174');

-- ===== SELF-HELP (1 libro) =====
INSERT INTO book (title, book_author, description, isbn, page_count, publisher, publication_year, created_at, added_by_id) VALUES
('Le 7 regole per avere successo', 'Stephen Covey', 'Un classico della crescita personale che insegna principi universali per l''efficacia nella vita personale e professionale.', '9788891741189', 400, 'Franco Angeli', 1989, NOW(), @user_id),
('Padre ricco padre povero', 'Robert Kiyosaki', 'Lezioni di educazione finanziaria attraverso le storie di due padri con filosofie opposte sul denaro.', '9788825407488', 336, 'Gribaudi', 1997, NOW(), @user_id),
('Pensieri lenti e veloci', 'Daniel Kahneman', 'Il Nobel per l''economia spiega i due sistemi di pensiero che guidano le nostre decisioni quotidiane.', '9788804667636', 548, 'Mondadori', 2011, NOW(), @user_id),
('Atomic Habits', 'James Clear', 'Piccole abitudini quotidiane possono portare a cambiamenti straordinari: una guida pratica per costruire buone abitudini.', '9788851197766', 320, 'De Agostini', 2018, NOW(), @user_id),
('Il potere di adesso', 'Eckhart Tolle', 'Una guida spirituale per vivere nel momento presente, liberandosi dall''ansia del futuro e dal peso del passato.', '9788863800036', 240, 'Armenia', 1997, NOW(), @user_id);

INSERT INTO book_genre (book_id, genre_id) SELECT b.id, 37 FROM book b WHERE b.isbn IN ('9788891741189','9788825407488','9788804667636','9788851197766','9788863800036');
-- Kahneman anche Scienza+Saggistica
INSERT INTO book_genre (book_id, genre_id) SELECT b.id, 40 FROM book b WHERE b.isbn = '9788804667636';
INSERT INTO book_genre (book_id, genre_id) SELECT b.id, 30 FROM book b WHERE b.isbn = '9788804667636';

-- ===== FUMETTO (1 libro) =====
INSERT INTO book (title, book_author, description, isbn, page_count, publisher, publication_year, created_at, added_by_id) VALUES
('Maus', 'Art Spiegelman', 'La storia dell''Olocausto raccontata attraverso un fumetto dove gli ebrei sono topi e i nazisti gatti. Premio Pulitzer.', '9788806173821', 296, 'Einaudi', 1991, NOW(), @user_id),
('Persepolis', 'Marjane Satrapi', 'L''infanzia e l''adolescenza dell''autrice durante la rivoluzione islamica in Iran, raccontata in bianco e nero.', '9788817026154', 352, 'Rizzoli', 2000, NOW(), @user_id),
('V per Vendetta', 'Alan Moore', 'In un''Inghilterra fascista del futuro, un misterioso anarchico mascherato lotta contro il regime totalitario.', '9788869718762', 296, 'Panini Comics', 1988, NOW(), @user_id),
('Watchmen', 'Alan Moore', 'In un''America alternativa, ex supereroi indagano sulla morte di un loro collega, scoprendo una cospirazione globale.', '9788869718779', 416, 'Panini Comics', 1986, NOW(), @user_id),
('Zerocalcare - Kobane Calling', 'Zerocalcare', 'Il fumettista romano racconta il suo viaggio al confine tra Turchia e Siria, tra i combattenti curdi contro l''ISIS.', '9788899016524', 272, 'Bao Publishing', 2016, NOW(), @user_id);

INSERT INTO book_genre (book_id, genre_id) SELECT b.id, 33 FROM book b WHERE b.isbn IN ('9788806173821','9788817026154','9788869718762','9788869718779','9788899016524');
-- Maus anche Storico+Biografia, V per Vendetta e Watchmen anche Dystopia
INSERT INTO book_genre (book_id, genre_id) SELECT b.id, 28 FROM book b WHERE b.isbn = '9788806173821';
INSERT INTO book_genre (book_id, genre_id) SELECT b.id, 29 FROM book b WHERE b.isbn = '9788806173821';
INSERT INTO book_genre (book_id, genre_id) SELECT b.id, 34 FROM book b WHERE b.isbn IN ('9788869718762','9788869718779');

-- ===== DYSTOPIA (2 libri) =====
INSERT INTO book (title, book_author, description, isbn, page_count, publisher, publication_year, created_at, added_by_id) VALUES
('Il mondo nuovo', 'Aldous Huxley', 'In un futuro dove gli esseri umani sono prodotti in laboratorio e condizionati alla felicitÃ  forzata, un uomo scopre la veritÃ .', '9788804667827', 288, 'Mondadori', 1932, NOW(), @user_id),
('La strada', 'Cormac McCarthy', 'Un padre e suo figlio attraversano un''America post-apocalittica, cercando di sopravvivere in un mondo senza speranza.', '9788806190538', 216, 'Einaudi', 2006, NOW(), @user_id),
('Noi', 'Evgenij Zamjatin', 'In uno Stato Unico di vetro e acciaio, un ingegnere inizia a provare emozioni proibite. Il romanzo che ispirÃ² Orwell.', '9788807900150', 224, 'Feltrinelli', 1924, NOW(), @user_id);

INSERT INTO book_genre (book_id, genre_id) SELECT b.id, 34 FROM book b WHERE b.isbn IN ('9788804667827','9788806190538','9788807900150');
-- Huxley e Zamjatin anche Classici+Fantascienza
INSERT INTO book_genre (book_id, genre_id) SELECT b.id, 36 FROM book b WHERE b.isbn IN ('9788804667827','9788807900150');
INSERT INTO book_genre (book_id, genre_id) SELECT b.id, 22 FROM book b WHERE b.isbn IN ('9788804667827','9788807900150');

-- ===== SCIENZA (2 libri) =====
INSERT INTO book (title, book_author, description, isbn, page_count, publisher, publication_year, created_at, added_by_id) VALUES
('Cosmo', 'Carl Sagan', 'Un viaggio attraverso l''universo: dalle galassie piÃ¹ remote alle molecole del DNA, raccontato dal piÃ¹ grande divulgatore scientifico.', '9788804667544', 432, 'Mondadori', 1980, NOW(), @user_id),
('L''universo elegante', 'Brian Greene', 'La teoria delle stringhe spiegata in modo accessibile: come la fisica cerca di unificare tutte le forze della natura.', '9788806176068', 480, 'Einaudi', 1999, NOW(), @user_id),
('Il gene egoista', 'Richard Dawkins', 'Una rivoluzionaria interpretazione dell''evoluzione vista dalla prospettiva dei geni, non degli organismi.', '9788804667452', 400, 'Mondadori', 1976, NOW(), @user_id);

INSERT INTO book_genre (book_id, genre_id) SELECT b.id, 40 FROM book b WHERE b.isbn IN ('9788804667544','9788806176068','9788804667452');
INSERT INTO book_genre (book_id, genre_id) SELECT b.id, 30 FROM book b WHERE b.isbn IN ('9788804667544','9788806176068','9788804667452');

-- ===== BIOGRAFIA (3 libri) =====
INSERT INTO book (title, book_author, description, isbn, page_count, publisher, publication_year, created_at, added_by_id) VALUES
('Diario di Anna Frank', 'Anna Frank', 'Il diario di una ragazza ebrea nascosta durante l''occupazione nazista dei Paesi Bassi. Una testimonianza indimenticabile.', '9788806219468', 320, 'Einaudi', 1947, NOW(), @user_id),
('Long Walk to Freedom', 'Nelson Mandela', 'L''autobiografia di Mandela: dall''infanzia rurale alla lotta contro l''apartheid, fino alla presidenza del Sudafrica.', '9788807886164', 672, 'Feltrinelli', 1994, NOW(), @user_id);

INSERT INTO book_genre (book_id, genre_id) SELECT b.id, 29 FROM book b WHERE b.isbn IN ('9788806219468','9788807886164');
INSERT INTO book_genre (book_id, genre_id) SELECT b.id, 28 FROM book b WHERE b.isbn IN ('9788806219468','9788807886164');

-- ===== SAGGISTICA (3 libri) =====
INSERT INTO book (title, book_author, description, isbn, page_count, publisher, publication_year, created_at, added_by_id) VALUES
('Homo Deus', 'Yuval Noah Harari', 'Dopo Sapiens, Harari guarda al futuro: intelligenza artificiale, biotecnologie e il destino della specie umana.', '9788845931475', 496, 'Bompiani', 2015, NOW(), @user_id),
('21 lezioni per il XXI secolo', 'Yuval Noah Harari', 'Harari affronta le sfide del presente: terrorismo, fake news, immigrazione e il futuro del lavoro nell''era digitale.', '9788845933622', 464, 'Bompiani', 2018, NOW(), @user_id);

INSERT INTO book_genre (book_id, genre_id) SELECT b.id, 30 FROM book b WHERE b.isbn IN ('9788845931475','9788845933622');

-- ===== FANTASY (3 libri) =====
INSERT INTO book (title, book_author, description, isbn, page_count, publisher, publication_year, created_at, added_by_id) VALUES
('Le cronache di Narnia', 'C.S. Lewis', 'Quattro fratelli scoprono un armadio magico che conduce nel mondo di Narnia, una terra incantata governata dal leone Aslan.', '9788804668497', 768, 'Mondadori', 1950, NOW(), @user_id),
('La ruota del tempo - L''occhio del mondo', 'Robert Jordan', 'Rand al''Thor e i suoi amici fuggono dal villaggio quando creature oscure li attaccano, iniziando un viaggio epico.', '9788834739600', 832, 'Fanucci', 1990, NOW(), @user_id);

INSERT INTO book_genre (book_id, genre_id) SELECT b.id, 21 FROM book b WHERE b.isbn IN ('9788804668497','9788834739600');
INSERT INTO book_genre (book_id, genre_id) SELECT b.id, 27 FROM book b WHERE b.isbn IN ('9788804668497','9788834739600');

-- ===== STORICO (3 libri) =====
INSERT INTO book (title, book_author, description, isbn, page_count, publisher, publication_year, created_at, added_by_id) VALUES
('I pilastri della terra', 'Ken Follett', 'Nell''Inghilterra del XII secolo, la costruzione di una cattedrale intreccia le vite di nobili, monaci e artigiani.', '9788804667308', 1072, 'Mondadori', 1989, NOW(), @user_id),
('Il conte di Montecristo', 'Alexandre Dumas', 'Edmond DantÃ¨s, ingiustamente imprigionato, evade e si reinventa come conte di Montecristo per vendicarsi dei suoi nemici.', '9788807900563', 1200, 'Feltrinelli', 1844, NOW(), @user_id);

INSERT INTO book_genre (book_id, genre_id) SELECT b.id, 28 FROM book b WHERE b.isbn IN ('9788804667308','9788807900563');
INSERT INTO book_genre (book_id, genre_id) SELECT b.id, 27 FROM book b WHERE b.isbn = '9788807900563';
INSERT INTO book_genre (book_id, genre_id) SELECT b.id, 36 FROM book b WHERE b.isbn = '9788807900563';

-- ===== YOUNG ADULT (4 libri) =====
INSERT INTO book (title, book_author, description, isbn, page_count, publisher, publication_year, created_at, added_by_id) VALUES
('Colpa delle stelle', 'John Green', 'Due adolescenti malati di cancro si innamorano e affrontano insieme la fragilitÃ  della vita con ironia e coraggio.', '9788817063210', 288, 'Rizzoli', 2012, NOW(), @user_id);

INSERT INTO book_genre (book_id, genre_id) SELECT b.id, 35 FROM book b WHERE b.isbn = '9788817063210';
INSERT INTO book_genre (book_id, genre_id) SELECT b.id, 26 FROM book b WHERE b.isbn = '9788817063210';

-- ===== ROMANZO (4 libri) =====
INSERT INTO book (title, book_author, description, isbn, page_count, publisher, publication_year, created_at, added_by_id) VALUES
('Anna Karenina', 'Lev Tolstoj', 'La tragica storia d''amore tra Anna Karenina e il conte Vronskij nella Russia imperiale. Un capolavoro sulla passione e le convenzioni sociali.', '9788806219475', 960, 'Einaudi', 1877, NOW(), @user_id);

INSERT INTO book_genre (book_id, genre_id) SELECT b.id, 26 FROM book b WHERE b.isbn = '9788806219475';
INSERT INTO book_genre (book_id, genre_id) SELECT b.id, 36 FROM book b WHERE b.isbn = '9788806219475';

-- ===== FANTASCIENZA (4 libri) =====
INSERT INTO book (title, book_author, description, isbn, page_count, publisher, publication_year, created_at, added_by_id) VALUES
('Neuromante', 'William Gibson', 'Case, un hacker decaduto, viene reclutato per un''ultima missione nel cyberspazio. Il romanzo che ha inventato il cyberpunk.', '9788804668565', 320, 'Mondadori', 1984, NOW(), @user_id);

INSERT INTO book_genre (book_id, genre_id) SELECT b.id, 22 FROM book b WHERE b.isbn = '9788804668565';


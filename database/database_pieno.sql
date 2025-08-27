-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Creato il: Ago 27, 2025 alle 19:57
-- Versione del server: 10.4.32-MariaDB
-- Versione PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `boxomnia`
--

DELIMITER $$
--
-- Procedure
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `pulisci_carrelli_abbandonati` (IN `giorni` INT)   BEGIN
    UPDATE carrello
    SET stato = 'abbandonato'
    WHERE stato = 'attivo'
      AND data_ultima_modifica < DATE_SUB(NOW(), INTERVAL giorni DAY)
      AND id_carrello NOT IN (
        SELECT fk_carrello FROM ordine WHERE fk_carrello IS NOT NULL
    );

    SELECT ROW_COUNT() as carrelli_aggiornati;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `report_vendite` (IN `data_inizio` DATE, IN `data_fine` DATE)   BEGIN
    SELECT
        DATE(o.data_ordine) as data,
        COUNT(DISTINCT o.id_ordine) as numero_ordini,
        COUNT(DISTINCT o.fk_utente) as clienti_unici,
        SUM(c.quantita) as articoli_venduti,
        SUM(c.totale) as fatturato_totale,
        AVG(c.totale) as valore_medio_ordine
    FROM ordine o
             LEFT JOIN carrello c ON o.fk_carrello = c.id_carrello
    WHERE DATE(o.data_ordine) BETWEEN data_inizio AND data_fine
      AND o.stato_ordine NOT IN (3, 4) -- Escludi annullati e rimborsati
    GROUP BY DATE(o.data_ordine)
    ORDER BY data DESC;
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Struttura della tabella `admin`
--

CREATE TABLE `admin` (
  `id_admin` int(11) NOT NULL,
  `fk_utente` int(11) NOT NULL,
  `livello_admin` varchar(50) NOT NULL DEFAULT 'admin',
  `data_creazione` datetime NOT NULL DEFAULT current_timestamp(),
  `creato_da` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `admin`
--

INSERT INTO `admin` (`id_admin`, `fk_utente`, `livello_admin`, `data_creazione`, `creato_da`) VALUES
(2, 2, 'super_admin', '2025-08-06 17:11:58', 2),
(3, 6, 'super_admin', '2025-08-06 17:33:24', NULL);

-- --------------------------------------------------------

--
-- Struttura della tabella `box_oggetto`
--

CREATE TABLE `box_oggetto` (
  `fk_box` int(11) NOT NULL,
  `fk_oggetto` int(11) NOT NULL,
  `probabilita` float NOT NULL,
  `quantita_min` int(11) NOT NULL DEFAULT 1,
  `quantita_max` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `box_oggetto`
--

INSERT INTO `box_oggetto` (`fk_box`, `fk_oggetto`, `probabilita`, `quantita_min`, `quantita_max`) VALUES
(2, 27, 0.3, 1, 2);

-- --------------------------------------------------------

--
-- Struttura della tabella `carrello`
--

CREATE TABLE `carrello` (
  `id_carrello` int(11) NOT NULL,
  `totale` decimal(10,2) NOT NULL DEFAULT 0.00,
  `fk_utente` int(11) NOT NULL,
  `data_creazione` datetime NOT NULL DEFAULT current_timestamp(),
  `data_ultima_modifica` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `quantita` bigint(20) NOT NULL,
  `stato` enum('attivo','checkout','completato','abbandonato') DEFAULT 'attivo',
  `fk_mystery_box` int(11) DEFAULT NULL,
  `fk_oggetto` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Trigger `carrello`
--
DELIMITER $$
CREATE TRIGGER `carrello_before_update` BEFORE UPDATE ON `carrello` FOR EACH ROW BEGIN
    SET NEW.data_ultima_modifica = NOW();
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Struttura della tabella `carrello_salvato`
--

CREATE TABLE `carrello_salvato` (
  `id_salvato` int(11) NOT NULL,
  `fk_utente` int(11) NOT NULL,
  `fk_mystery_box` int(11) DEFAULT NULL,
  `fk_oggetto` int(11) DEFAULT NULL,
  `quantita` int(11) NOT NULL DEFAULT 1,
  `data_aggiunta` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `carrello_utente`
--

CREATE TABLE `carrello_utente` (
  `fk_carrello` int(11) NOT NULL,
  `fk_utente` int(11) NOT NULL,
  `quantita` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `categoria_oggetto`
--

CREATE TABLE `categoria_oggetto` (
  `id_categoria` int(11) NOT NULL,
  `nome_categoria` varchar(50) NOT NULL,
  `tipo_oggetto` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `categoria_oggetto`
--

INSERT INTO `categoria_oggetto` (`id_categoria`, `nome_categoria`, `tipo_oggetto`) VALUES
(1, 'Yu-Gi-Oh!', 'Carta Singola'),
(2, 'Pokémon', 'Carta Singola'),
(3, 'Universale', 'Proteggicarte'),
(4, 'Universale', 'Plance di gioco'),
(5, 'Universale', 'Scatole porta carte'),
(6, 'Universale', 'Porta mazzi'),
(7, 'Pokémon', 'Mystery Box'),
(8, 'Yu-Gi-Oh!', 'Mystery Box'),
(9, 'Pokémon', 'Funko Pop'),
(10, 'Yu-Gi-Oh!', 'Funko Pop'),
(11, 'Pokémon', 'Bustina Singola - Astri Lucenti');

-- --------------------------------------------------------

--
-- Struttura della tabella `classifica`
--

CREATE TABLE `classifica` (
  `id_classifica` int(11) NOT NULL,
  `nome_classifica` varchar(100) NOT NULL,
  `tipo_classifica` varchar(50) NOT NULL,
  `desc_classifica` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `fattura`
--

CREATE TABLE `fattura` (
  `id_fattura` int(11) NOT NULL,
  `tipo` varchar(100) NOT NULL,
  `totale_fattura` decimal(10,2) NOT NULL,
  `data_emissione` datetime NOT NULL,
  `fk_utente` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `immagine`
--

CREATE TABLE `immagine` (
  `id_immagine` int(11) NOT NULL,
  `nome_img` varchar(100) DEFAULT NULL,
  `descrizione_img` varchar(100) DEFAULT NULL,
  `dimensione` int(11) DEFAULT NULL,
  `fk_oggetto` int(11) DEFAULT NULL,
  `fk_mystery_box` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `immagine`
--

INSERT INTO `immagine` (`id_immagine`, `nome_img`, `descrizione_img`, `dimensione`, `fk_oggetto`, `fk_mystery_box`) VALUES
(1, 'mago_nero.jpg', NULL, NULL, 1, NULL),
(2, 'plancia_arancione.png', NULL, NULL, 3, NULL),
(4, 'plancia_rosa.png', NULL, NULL, 4, NULL),
(5, 'plancia_viola.png', NULL, NULL, 5, NULL),
(6, 'plancia_verdeacqua.png', NULL, NULL, 6, NULL),
(7, 'proteggicarte_rosa.png', NULL, NULL, 7, NULL),
(8, 'proteggicarte_bluchiaro.png', NULL, NULL, 8, NULL),
(9, 'proteggicarte_rosso.png', NULL, NULL, 9, NULL),
(10, 'proteggicarte_bianco.png', NULL, NULL, 10, NULL),
(11, 'scatola_rosa.png', NULL, NULL, 17, NULL),
(12, 'scatola_verde.png', NULL, NULL, 12, NULL),
(13, 'scatola_blu.png', NULL, NULL, 14, NULL),
(14, 'scatola_rossa.png', NULL, NULL, 13, NULL),
(15, 'scatola_arancione.png', NULL, NULL, 11, NULL),
(16, 'scatola_oro.png', NULL, NULL, 15, NULL),
(17, 'scatola_argento.png', NULL, NULL, 16, NULL),
(18, 'scatola_marrone.png', NULL, NULL, 18, NULL),
(19, 'deckbox_giallo.png', NULL, NULL, 25, NULL),
(20, 'deckbox_verde.png', NULL, NULL, 23, NULL),
(21, 'deckbox_nero.png', NULL, NULL, 24, NULL),
(22, 'deckbox_viola.png', NULL, NULL, 19, NULL),
(23, 'deckbox_arancione.png', NULL, NULL, 20, NULL),
(24, 'deckbox_bianco.png', NULL, NULL, 21, NULL),
(25, 'deckbox_blu.png', NULL, NULL, 22, NULL),
(26, 'deckbox_rosa.png', NULL, NULL, 26, NULL),
(47, 'funko_umbreon.png', NULL, NULL, 28, NULL),
(48, 'funko_pikachu.png', NULL, NULL, 29, NULL),
(49, 'funko_charizard.png', NULL, NULL, 30, NULL),
(50, 'funko_mewtwo.png', NULL, NULL, 31, NULL),
(51, 'funko_dragonite.png', NULL, NULL, 32, NULL),
(52, 'funko_lucario.png', NULL, NULL, 33, NULL),
(53, 'funko_greninja.png', NULL, NULL, 34, NULL),
(54, 'funko_gengar.png', NULL, NULL, 35, NULL),
(55, 'funko_espeon.png', NULL, NULL, 36, NULL),
(56, 'funko_gardevoir.png', NULL, NULL, 37, NULL),
(57, 'funko_luxray.png', NULL, NULL, 38, NULL),
(58, 'funko_alakazam.png', NULL, NULL, 39, NULL),
(59, 'funko_sylveon.png', NULL, NULL, 40, NULL),
(60, 'funko_horsea.png', NULL, NULL, 41, NULL),
(61, 'funko_blaziken.png', NULL, NULL, 42, NULL),
(62, 'funko_chimchar.png', NULL, NULL, 43, NULL),
(63, 'funko_oshawott.png', NULL, NULL, 44, NULL),
(64, 'funko_piplup.png', NULL, NULL, 45, NULL),
(65, 'funko_sprigatito.png', NULL, NULL, 46, NULL),
(66, 'funko_zorua.png', NULL, NULL, 47, NULL),
(87, 'bebe.png', NULL, NULL, 48, NULL),
(88, 'lame.png', NULL, NULL, 49, NULL),
(89, 'foresta.png', NULL, NULL, 50, NULL),
(90, 'sole.png', NULL, NULL, 51, NULL),
(91, 'pietra.png', NULL, NULL, 52, NULL),
(92, 'fantasma.png', NULL, NULL, 53, NULL),
(93, 'cristallo.png', NULL, NULL, 54, NULL),
(94, 'ghiaccio.png', NULL, NULL, 55, NULL),
(95, 'occhiblu.png', NULL, NULL, 56, NULL),
(96, 'celestiale.png', NULL, NULL, 57, NULL),
(97, 'ombre.png', NULL, NULL, 58, NULL),
(98, 'eterna.png', NULL, NULL, 59, NULL),
(99, 'sigilli.png', NULL, NULL, 60, NULL),
(100, 'abissi.png', NULL, NULL, 61, NULL),
(101, 'imperatore.png', NULL, NULL, 62, NULL),
(102, 'giudizio.png', NULL, NULL, 63, NULL),
(103, 'oscuri.png', NULL, NULL, 64, NULL),
(104, 'proibito.png', NULL, NULL, 65, NULL),
(105, 'ra.png', NULL, NULL, 66, NULL),
(106, 'tormentatore.png', NULL, NULL, 67, NULL),
(107, 'pikachu.png', NULL, NULL, 2, NULL),
(108, 'caterpie.png', NULL, NULL, 68, NULL),
(109, 'charmander.png', NULL, NULL, 69, NULL),
(110, 'squirtle.png', NULL, NULL, 70, NULL),
(111, 'bulbasaur.png', NULL, NULL, 71, NULL),
(112, 'eevee.png', NULL, NULL, 72, NULL),
(113, 'raichu.png', NULL, NULL, 73, NULL),
(114, 'arcanine.png', NULL, NULL, 74, NULL),
(115, 'lapras.png', NULL, NULL, 75, NULL),
(116, 'charizard.png', NULL, NULL, 76, NULL),
(117, 'gengar.png', NULL, NULL, 77, NULL),
(118, 'dragonite.png', NULL, NULL, 78, NULL),
(119, 'mew.png', NULL, NULL, 79, NULL),
(120, 'lucario.png', NULL, NULL, 80, NULL),
(121, 'gyarados.png', NULL, NULL, 81, NULL),
(122, 'mewtwo.png', NULL, NULL, 82, NULL),
(123, 'rayquaza.png', NULL, NULL, 83, NULL),
(124, 'darkrai.png', NULL, NULL, 84, NULL),
(125, 'reshiram.png', NULL, NULL, 85, NULL),
(126, 'zekrom.png', NULL, NULL, 86, NULL),
(127, 'giratina.png', NULL, NULL, 87, NULL),
(128, 'funko_jaden.png', NULL, NULL, 88, NULL),
(129, 'funko_yubel.png', NULL, NULL, 89, NULL),
(130, 'funko_alexis.png', NULL, NULL, 90, NULL),
(131, 'funko_seto.png', NULL, NULL, 91, NULL),
(132, 'funko_yami.png', NULL, NULL, 92, NULL),
(133, 'funko_dark.png', NULL, NULL, 93, NULL),
(134, 'funko_rainbow.png', NULL, NULL, 94, NULL),
(135, 'funko_cyber.png', NULL, NULL, 95, NULL),
(136, 'funko_neos.png', NULL, NULL, 96, NULL),
(137, 'funko_kuribo.png', NULL, NULL, 97, NULL),
(138, 'funko_harpie.png', NULL, NULL, 98, NULL),
(139, 'funko_joey.png', NULL, NULL, 99, NULL),
(140, 'funko_zane.png', NULL, NULL, 100, NULL),
(141, 'funko_ojama.png', NULL, NULL, 101, NULL),
(142, 'funko_harpie3.png', NULL, NULL, 102, NULL),
(143, 'funko_avian.png', NULL, NULL, 103, NULL),
(144, 'funko_jinzo.png', NULL, NULL, 104, NULL),
(145, 'funko_harpiepet.png', NULL, NULL, 105, NULL);

-- --------------------------------------------------------

--
-- Struttura della tabella `indirizzo_spedizione`
--

CREATE TABLE `indirizzo_spedizione` (
  `id_indirizzo` int(11) NOT NULL,
  `via` varchar(100) NOT NULL,
  `civico` bigint(20) NOT NULL,
  `cap` bigint(20) NOT NULL,
  `citta` varchar(100) NOT NULL,
  `nazione` varchar(100) NOT NULL,
  `provincia` varchar(100) NOT NULL,
  `fk_utente` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `indirizzo_spedizione`
--

INSERT INTO `indirizzo_spedizione` (`id_indirizzo`, `via`, `civico`, `cap`, `citta`, `nazione`, `provincia`, `fk_utente`) VALUES
(1, 'Ticino', 4, 64028, 'Silvi', 'Italia', 'Teramo', 1);

-- --------------------------------------------------------

--
-- Struttura della tabella `info_ordine`
--

CREATE TABLE `info_ordine` (
  `fk_ordine` int(11) NOT NULL,
  `fk_box` int(11) NOT NULL,
  `quantita_ordine` int(11) NOT NULL,
  `totale_ordine` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `info_ordine`
--

INSERT INTO `info_ordine` (`fk_ordine`, `fk_box`, `quantita_ordine`, `totale_ordine`) VALUES
(2, 2, 1, 20.00),
(3, 2, 2, 40.00);

-- --------------------------------------------------------

--
-- Struttura della tabella `mystery_box`
--

CREATE TABLE `mystery_box` (
  `id_box` int(11) NOT NULL,
  `nome_box` varchar(100) NOT NULL,
  `desc_box` text NOT NULL,
  `prezzo_box` decimal(10,2) NOT NULL,
  `quantita_box` int(11) NOT NULL,
  `fk_rarita` int(11) NOT NULL,
  `fk_categoria_oggetto` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `mystery_box`
--

INSERT INTO `mystery_box` (`id_box`, `nome_box`, `desc_box`, `prezzo_box`, `quantita_box`, `fk_rarita`, `fk_categoria_oggetto`) VALUES
(2, 'PokéBox - Rara', 'Mystery Box che potrebbe contenere carte singole, bustine e/o starter pack di rarità: rara', 20.00, 2, 2, 7);

-- --------------------------------------------------------

--
-- Struttura della tabella `novita_box`
--

CREATE TABLE `novita_box` (
  `fk_mystery_box` int(11) NOT NULL,
  `data_novita` datetime NOT NULL,
  `desc_novita` varchar(100) DEFAULT NULL,
  `sconto_novita` decimal(10,0) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `novita_oggetto`
--

CREATE TABLE `novita_oggetto` (
  `fk_oggetto` int(11) NOT NULL,
  `novita_data` datetime NOT NULL,
  `novita_desc` varchar(100) DEFAULT NULL,
  `novita_sconto` decimal(10,0) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `novita_oggetto`
--

INSERT INTO `novita_oggetto` (`fk_oggetto`, `novita_data`, `novita_desc`, `novita_sconto`) VALUES
(30, '2025-08-27 16:28:49', NULL, 50),
(32, '2025-08-27 18:07:50', NULL, 75),
(34, '2025-08-27 18:12:16', NULL, 12),
(36, '2025-08-27 18:12:16', NULL, 3),
(38, '2025-08-27 18:13:03', NULL, 30),
(39, '2025-08-27 16:25:49', NULL, 10),
(90, '2025-08-27 16:28:24', NULL, 20),
(93, '2025-08-27 18:57:54', 'Uno dei più ricercati!', NULL),
(97, '2025-08-27 18:29:50', NULL, 1),
(103, '2025-08-27 16:27:34', NULL, 5);

-- --------------------------------------------------------

--
-- Struttura della tabella `oggetto`
--

CREATE TABLE `oggetto` (
  `id_oggetto` int(11) NOT NULL,
  `nome_oggetto` varchar(100) NOT NULL,
  `desc_oggetto` text NOT NULL,
  `prezzo_oggetto` decimal(10,2) DEFAULT NULL,
  `quant_oggetto` int(11) DEFAULT NULL,
  `fk_categoria_oggetto` int(11) NOT NULL,
  `fk_rarita` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `oggetto`
--

INSERT INTO `oggetto` (`id_oggetto`, `nome_oggetto`, `desc_oggetto`, `prezzo_oggetto`, `quant_oggetto`, `fk_categoria_oggetto`, `fk_rarita`) VALUES
(1, 'Mago Nero', 'Mostro di tipo Incantesimo', NULL, NULL, 1, 3),
(2, 'Pikachu', 'Pokémon di tipo Elettro', NULL, NULL, 2, 1),
(3, 'Plancia di gioco Arancione', 'Tappetino universale per giochi di carte collezionabili, realizzato in neoprene resistente con base antiscivolo.\nDimensione: 60x35cm', 14.99, 5, 4, NULL),
(4, 'Plancia di gioco Rosa', 'Tappetino universale per giochi di carte collezionabili, realizzato in neoprene resistente con base antiscivolo.\nDimensione: 60x35cm', 14.50, 5, 4, NULL),
(5, 'Plancia di gioco Viola', 'Tappetino universale per giochi di carte collezionabili, realizzato in neoprene resistente con base antiscivolo.\nDimensione: 60x35cm', 14.99, 5, 4, NULL),
(6, 'Plancia di gioco Verdeacqua', 'Tappetino universale per giochi di carte collezionabili, realizzato in neoprene resistente con base antiscivolo.\nDimensione: 60x35cm', 14.99, 5, 4, NULL),
(7, 'Proteggicarte Rosa', 'Pacchetto proteggicarte da 60 bustine.\nDimensione bustine: 62x89mm', 3.00, 5, 3, NULL),
(8, 'Proteggicarte Blu chiaro', 'Pacchetto proteggicarte da 60 bustine.\nDimensione bustine: 62x89mm', 4.00, 5, 3, NULL),
(9, 'Proteggicarte Rosso', 'Pacchetto proteggicarte da 60 bustine.\nDimensione bustine: 62x89mm', 3.50, 5, 3, NULL),
(10, 'Proteggicarte Bianco', 'Pacchetto proteggicarte da 60 bustine.\nDimensione bustine: 62x89mm', 4.50, 5, 3, NULL),
(11, 'Scatola porta carte Arancione', 'Scatola porta carte a incastro con capienza di 100 carte e bustine', 3.50, 5, 5, NULL),
(12, 'Scatola porta carte Verde', 'Scatola porta carte a incastro con capienza di 100 carte e bustine', 3.50, 5, 5, NULL),
(13, 'Scatola porta carte Rossa', 'Scatola porta carte a incastro con capienza di 100 carte e bustine', 3.50, 5, 5, NULL),
(14, 'Scatola porta carte Blu', 'Scatola porta carte a incastro con capienza di 100 carte e bustine', 3.50, 5, 5, NULL),
(15, 'Scatola porta carte Oro', 'Scatola porta carte a incastro con capienza di 100 carte e bustine', 5.00, 5, 5, NULL),
(16, 'Scatola porta carte Argento', 'Scatola porta carte a incastro con capienza di 100 carte e bustine', 4.00, 5, 5, NULL),
(17, 'Scatola porta carte Rosa', 'Scatola porta carte a incastro con capienza di 100 carte e bustine', 3.00, 5, 5, NULL),
(18, 'Scatola porta carte Marrone', 'Scatola porta carte a 4 scomparti con capienza di 300 carte e bustine', 9.50, 5, 5, NULL),
(19, 'Porta mazzo Viola', 'Porta mazzo universale in plastica rigida, con capienza fino a 75 carte con bustine protettive. Protezione da polvere e piegature', 3.00, 5, 6, NULL),
(20, 'Porta mazzo Arancione', 'Porta mazzo universale in plastica rigida, con capienza fino a 75 carte con bustine protettive. Protezione da polvere e piegature', 3.00, 5, 6, NULL),
(21, 'Porta mazzo Bianco', 'Porta mazzo universale in plastica rigida, con capienza fino a 75 carte con bustine protettive. Protezione da polvere e piegature', 4.00, 5, 6, NULL),
(22, 'Porta mazzo Blu chiaro', 'Porta mazzo universale in plastica rigida, con capienza fino a 75 carte con bustine protettive. Protezione da polvere e piegature', 3.00, 5, 6, NULL),
(23, 'Porta mazzo Verde', 'Porta mazzo universale in plastica rigida, con capienza fino a 75 carte con bustine protettive. Protezione da polvere e piegature', 3.00, 5, 6, NULL),
(24, 'Porta mazzo Nero', 'Porta mazzo universale in plastica rigida, con capienza fino a 75 carte con bustine protettive. Protezione da polvere e piegature', 4.00, 5, 6, NULL),
(25, 'Porta mazzo Giallo', 'Porta mazzo universale in plastica rigida, con capienza fino a 75 carte con bustine protettive. Protezione da polvere e piegature', 3.50, 5, 6, NULL),
(26, 'Porta mazzo Rosa', 'Porta mazzo universale in plastica rigida, con capienza fino a 75 carte con bustine protettive. Protezione da polvere e piegature', 3.00, 5, 6, NULL),
(27, 'Bustina Singola - Astri Lucenti', 'Bustina Pokémon della serie Astri Lucenti', NULL, NULL, 11, NULL),
(28, 'Funko POP: Umbreon', 'Funko POP da collezione di Umbreon, Pokémon di tipo Buio, famoso per il suo manto nero e gli anelli luminescenti che brillano nella notte. Dimensione: 9,5cm', 15.50, 5, 9, NULL),
(29, 'Funko POP: Pikachu', 'Funko POP da collezione di Pikachu, l\'iconico Pokémon di tipo Elettro, amato per la sua energia travolgente e il sorriso inconfondibile. Dimensione: 9,5cm', 15.00, 5, 9, NULL),
(30, 'Funko POP: Charizard', 'Funko POP da collezione di Charizard, potente Pokémon di tipo Fuoco/Volante, temuto per le sue fiamme intense e il volo maestoso. Dimensione: 9,5cm', 15.50, 5, 9, NULL),
(31, 'Funko POP: Mewtwo', 'Funko POP da collezione di Mewtwo, leggendario Pokémon Psico, creato in laboratorio e dotato di poteri mentali straordinari.Dimensione: 9,5cm', 16.00, 5, 9, NULL),
(32, 'Funko POP: Dragonite', 'Funko POP da collezione di Dragonite, maestoso Pokémon Drago/Volante, noto per la sua forza impressionante e il cuore gentile. Dimensione: 9,5cm', 16.00, 5, 9, NULL),
(33, 'Funko POP: Lucario', 'Funko POP da collezione di Lucario, fiero Pokémon Lotta/Acciaio, capace di percepire e controllare le aure con incredibile maestria. Dimensione: 9,5cm', 15.00, 5, 9, NULL),
(34, 'Funko POP: Greninja', 'Funko POP da collezione di Greninja, Pokémon Acqua/Buio, famoso per la sua velocità e lo stile di combattimento furtivo. Dimensione: 9,5cm', 15.00, 5, 9, NULL),
(35, 'Funko POP: Gengar', 'Funko POP da collezione di Gengar, enigmatico Pokémon Spettro/Veleno, celebre per i suoi scherzi inquietanti e il sorriso sinistro. Dimensione: 9,5cm', 15.50, 5, 9, NULL),
(36, 'Funko POP: Espeon', 'Funko POP da collezione di Espeon, elegante Pokémon Psico, apprezzato per la sua grazia e le abilità predittive straordinarie. Dimensione: 9,5cm', 15.50, 5, 9, NULL),
(37, 'Funko POP: Gardevoir', 'Funko POP da collezione di Gardevoir, elegante Pokémon di tipo Psichico/Folletto, noto per la sua grazia e capacità di proteggere il proprio allenatore con poteri psichici potenti. Dimensione: 9,5cm', 15.00, 5, 9, NULL),
(38, 'Funko POP: Luxray', 'Funko POP da collezione di Luxray, Pokémon di tipo Elettro, simile a una grossa tigre dai occhi penetranti, famoso per la sua vista acutissima e la forza elettrica che usa in battaglia. Dimensione: 9,5cm', 15.50, 5, 9, NULL),
(39, 'Funko POP: Alakazam', 'Funko POP da collezione di Alakazam, Pokémon di tipo Psichico, famoso per il suo intelletto straordinario e i potenti poteri psichici, rappresentato con cucchiai per amplificare la telecinesi. Dimensione: 9,5cm', 15.00, 5, 9, NULL),
(40, 'Funko POP: Sylveon', 'Funko POP da collezione di Sylveon, Pokémon di tipo Folletto, noto per il suo aspetto dolce e i nastri che usa per calmare e proteggere gli altri con poteri magici. Dimensione: 9,5cm', 15.50, 5, 9, NULL),
(41, 'Funko POP: Horsea', 'Funko POP da collezione di Horsea, Pokémon di tipo Acqua, simile a un piccolo cavalluccio marino, capace di spruzzare getti d\'acqua ad alta pressione per difendersi. Dimensione: 9,5cm', 14.50, 5, 9, NULL),
(42, 'Funko POP: Blaziken', 'Funko POP da collezione di Blaziken, Pokémon di tipo Fuoco/Lotta, famoso per la sua forza esplosiva e le potenti tecniche di arti marziali che combina con fiamme avvolgenti. Dimensione: 9,5cm', 14.99, 5, 9, NULL),
(43, 'Funko POP: Chimchar', 'Funko POP da collezione di Chimchar, Pokémon di tipo Fuoco, noto per la sua coda fiammeggiante e il carattere energico e molto curioso. Dimensione: 9,5cm', 15.00, 5, 9, NULL),
(44, 'Funko POP: Oshawott', 'Funko POP da collezione di Oshawott, Pokémon di tipo Acqua, noto per il suo guscio a forma di conchiglia che usa come arma e per il suo spirito coraggioso. Dimensione: 9,5cm', 15.00, 5, 9, NULL),
(45, 'Funko POP: Piplup', 'Funko POP da collezione di Piplup, Pokémon di tipo Acqua, noto per il suo carattere orgoglioso e il coraggio nonostante la sua taglia ridotta. Dimensione: 9,5cm', 15.00, 5, 9, NULL),
(46, 'Funko POP: Sprigatito', 'Funko POP da collezione di Sprigatito, Pokémon di tipo Erba, noto per il suo atteggiamento molto curioso e notevolmente affettuoso. Dimensione: 9,5cm', 15.00, 5, 9, NULL),
(47, 'Funko POP: Zorua', 'Funko POP da collezione di Zorua, Pokémon di tipo Buio, noto per la sua abilità nel creare illusioni e trasformarsi per ingannare i nemici. Dimensione: 9,5cm', 15.50, 5, 9, NULL),
(48, 'Drago Bebè', 'Mostro di tipo Drago', NULL, NULL, 1, 1),
(49, 'Combattente delle Lame', 'Mostro di tipo Guerriero', NULL, NULL, 1, 1),
(50, 'Folletto della Foresta', 'Mostro di tipo Pianta', NULL, NULL, 1, 1),
(51, 'Eda Maga del Sole', 'Mostro di tipo Fata', NULL, NULL, 1, 1),
(52, 'Golem Blocco', 'Mostro di tipo Roccia', NULL, NULL, 1, 1),
(53, 'Spadaccino Mistico LV6', 'Mostro di tipo Guerriero', NULL, NULL, 1, 2),
(54, 'Serpente Divino', 'Mostro di tipo Rettile', NULL, NULL, 1, 2),
(55, 'Cavaliere di Ghiaccio', 'Mostro di tipo Guerriero', NULL, NULL, 1, 2),
(56, 'Drago Bianco Occhi Blu', 'Mostro di tipo Drago', NULL, NULL, 1, 3),
(57, 'Arciere Celestiale', 'Mostro di tipo Fata', NULL, NULL, 1, 3),
(58, 'Monarca delle Ombre', 'Mostro di tipo Demone', NULL, NULL, 1, 3),
(59, 'Fenice Sacra', 'Mostro di tipo Bestia Alata', NULL, NULL, 1, 4),
(60, 'Guardiano Celtico', 'Mostro di tipo Guerriero', NULL, NULL, 1, 4),
(61, 'Drago Leviatano', 'Mostro di tipo Serpente Marino', NULL, NULL, 1, 4),
(62, 'Drago Divino Sirf', 'Mostro di tipo Drago', NULL, NULL, 1, 5),
(63, 'Angelo del Caos', 'Mostro di tipo Fata', NULL, NULL, 1, 5),
(64, 'Re Distruggi Memoria', 'Mostro di tipo Demone', NULL, NULL, 1, 5),
(65, 'Exodia il Proibito', 'Mostro di tipo Incantesimo', NULL, NULL, 1, 6),
(66, 'Drago Alato di Ra', 'Mostro di tipo Divino-Bestia', NULL, NULL, 1, 6),
(67, 'Obelisk il Tormentatore', 'Mostro di tipo Divino-Bestia', NULL, NULL, 1, 6),
(68, 'Caterpie', 'Pokémon di tipo Erba', NULL, NULL, 2, 1),
(69, 'Charmander', 'Pokémon di tipo Fuoco', NULL, NULL, 2, 1),
(70, 'Squirtle', 'Pokémon di tipo Acqua', NULL, NULL, 2, 1),
(71, 'Bulbasaur', 'Pokémon di tipo Erba', NULL, NULL, 2, 1),
(72, 'Eevee', 'Pokémon di tipo Normale', NULL, NULL, 2, 1),
(73, 'Raichu', 'Pokémon di tipo Elettro', NULL, NULL, 2, 2),
(74, 'Arcanine', 'Pokémon di tipo Fuoco', NULL, NULL, 2, 2),
(75, 'Lapras', 'Pokémon di tipo Acqua', NULL, NULL, 2, 2),
(76, 'Charizard', 'Pokémon di tipo Fuoco', NULL, NULL, 2, 3),
(77, 'Gengar', 'Pokémon di tipo Spettro', NULL, NULL, 2, 3),
(78, 'Dragonite', 'Pokémon di tipo Drago', NULL, NULL, 2, 3),
(79, 'Mew', 'Pokémon di tipo Psico', NULL, NULL, 2, 4),
(80, 'Lucario', 'Pokémon di tipo Lotta/Acciaio', NULL, NULL, 2, 4),
(81, 'Gyarados', 'Pokémon di tipo Acqua', NULL, NULL, 2, 4),
(82, 'Mewtwo', 'Pokémon di tipo Psico', NULL, NULL, 2, 5),
(83, 'Rayquaza', 'Pokémon di tipo Drago', NULL, NULL, 2, 5),
(84, 'Darkrai', 'Pokémon di tipo Buio', NULL, NULL, 2, 5),
(85, 'Reshiram', 'Pokémon di tipo Drago/Fuoco', NULL, NULL, 2, 6),
(86, 'Zekrom', 'Pokémon di tipo Drago/Elettro', NULL, NULL, 2, 6),
(87, 'Giratina', 'Pokémon di tipo Spettro/Drago', NULL, NULL, 2, 6),
(88, 'Funko POP: Jaden Yuki', 'Funko Pop da collezione di Jaden Yuki, protagonista di Yu-Gi-Oh! GX, raffigurato con la sua divisa rossa della Duel Academy e lo sguardo deciso da vero duellante. Dimensione: 9,5cm.\r\n', 16.00, 5, 10, NULL),
(89, 'Funko POP: Yubel', 'Funko Pop da collezione di Yubel, l’enigmatica creatura di Yu-Gi-Oh! GX, raffigurata con dettagli fedeli alle sue forme mostruose e al suo aspetto oscuro. Dimensione: 9,5cm.\r\n', 15.00, 5, 10, NULL),
(90, 'Funko POP: Alexis Rhodes', 'Funko Pop da collezione di Alexis Rhodes, elegante duellante di Yu-Gi-Oh! GX, rappresentata con la divisa della Duel Academy e il suo atteggiamento sicuro e determinato. Dimensione: 9,5cm.\r\n', 15.00, 5, 10, NULL),
(91, 'Funko POP: Seto Kaiba', 'Funko Pop da collezione di Seto Kaiba, il geniale e ambizioso duellante di Yu-Gi-Oh!, raffigurato con il suo iconico cappotto blu e lo sguardo fiero da rivale temibile. Dimensione: 9,5cm.\n', 15.50, 5, 10, NULL),
(92, 'Funko POP: Yami Yugi', 'Funko Pop da collezione di Yami Yugi, il leggendario duellante di Yu-Gi-Oh!, raffigurato con i capelli bicolore iconici, il Puzzle Millenario e lo sguardo deciso da campione. Dimensione: 9,5cm.\r\n', 20.00, 5, 10, NULL),
(93, 'Funko POP: Dark Magician', 'Funko Pop da collezione di Dark Magician, il potente incantatore di Yu-Gi-Oh!, raffigurato con il suo costume viola e il bastone magico pronto a lanciare incantesimi. Dimensione: 9,5cm.\r\n', 20.00, 5, 10, NULL),
(94, 'Funko POP: Rainbow Dragon', 'Funko Pop da collezione di Rainbow Dragon, il maestoso drago di Yu-Gi-Oh!, raffigurato con le ali variopinte e il corpo luminoso pronto a incantare ogni collezionista. Dimensione: 9,5cm.\r\n', 35.00, 2, 10, NULL),
(95, 'Funko POP: Cyber Dragon', 'Funko Pop da collezione di Cyber Dragon, il leggendario drago meccanico di Yu-Gi-Oh!, raffigurato con il corpo argentato e il design futuristico pronto per il duello. Dimensione: 9,5cm.\r\n', 17.00, 4, 10, NULL),
(96, 'Funko POP: Neos', 'Funko Pop da collezione di Elemental HERO Neos, l’eroe leggendario di Yu-Gi-Oh! GX, raffigurato con la sua armatura bianca e rosa e l’aspetto eroico pronto a combattere. Dimensione: 9,5cm.\r\n', 19.00, 3, 10, NULL),
(97, 'Funko POP: Kuribo', 'Funko Pop da collezione di Kuriboh, la piccola e adorabile creatura di Yu-Gi-Oh!, raffigurata con il suo corpo marrone peloso e gli occhi grandi e espressivi. Dimensione: 9,5cm.\r\n', 15.00, 5, 10, NULL),
(98, 'Funko POP: Harpie Lady', 'Funko Pop da collezione di Harpie Lady, l’agile e temibile guerriera alata di Yu-Gi-Oh!, raffigurata con le piume blu e il caratteristico sguardo deciso. Dimensione: 9,5cm.\n', 15.00, 5, 10, NULL),
(99, 'Funko POP: Joey Wheeler', 'Funko Pop da collezione di Joey Wheeler, il leale e coraggioso duellante di Yu-Gi-Oh!, raffigurato con i capelli biondi, la giacca verde e lo sguardo da vero amico di Yugi. Dimensione: 9,5cm.\n', 15.00, 5, 10, NULL),
(100, 'Funko POP: Zane Truesdale', 'Funko Pop da collezione di Zane Truesdale, il talentuoso e strategico duellante di Yu-Gi-Oh! GX, raffigurato con la divisa chiara della Duel Academy e lo sguardo concentrato. Dimensione: 9,5cm.\n', 15.00, 5, 10, NULL),
(101, 'Funko POP: Ojama Yellow', 'Funko Pop da collezione di Ojama Yellow, il buffo e divertente mostriciattolo di Yu-Gi-Oh!, raffigurato con il corpo giallo, le espressioni comiche e il suo caratteristico sorriso. Dimensione: 9,5cm.\r\n', 15.00, 5, 10, NULL),
(102, 'Funko POP: Harpie Lady 3', 'Funko Pop da collezione di Harpie Lady 3, l’elegante guerriera alata di Yu-Gi-Oh!, raffigurata con le piume viola, il costume caratteristico e lo sguardo determinato da duellante temibile. Dimensione: 9,5cm.\r\n', 23.00, 5, 10, NULL),
(103, 'Funko POP: Avian', 'Funko Pop da collezione di Avian, il maestoso mostro alato di Yu-Gi-Oh!, raffigurato con le ali spiegate e il corpo verde pronto al duello. Dimensione: 9,5cm.\n', 35.00, 2, 10, NULL),
(104, 'Funko POP: Jinzo e Time Wizard', 'Funko Pop da collezione di Jinzo, il potente duellante meccanico di Yu-Gi-Oh! e Time Wizard, il piccolo ma potente incantatore di Yu-Gi-Oh!. Dimensione: 9,5cm.', 40.00, 3, 10, NULL),
(105, 'Funko POP: Harpie\'s Pet Dragon', 'Funko Pop da collezione di Harpie’s Pet Dragon, il drago alato di Yu-Gi-Oh!, raffigurato con le ali, il corpo sinuoso e lo sguardo feroce pronto al duello. Dimensione: 9,5cm.\r\n', 25.00, 5, 10, NULL);

-- --------------------------------------------------------

--
-- Struttura della tabella `oggetto_collezione`
--

CREATE TABLE `oggetto_collezione` (
  `fk_oggetto` int(11) NOT NULL,
  `numero_carta` varchar(100) NOT NULL,
  `valore_stimato` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `oggetto_collezione`
--

INSERT INTO `oggetto_collezione` (`fk_oggetto`, `numero_carta`, `valore_stimato`) VALUES
(1, 'YGO-001', 10.00),
(2, 'PKM-001', 1.00),
(48, 'YGO-002', 0.50),
(49, 'YGO-003', 0.60),
(50, 'YGO-004', 0.40),
(51, 'YGO-005', 0.70),
(52, 'YGO-006', 0.55),
(53, 'YGO-007', 2.50),
(54, 'YGO-008', 3.00),
(55, 'YGO-009', 2.80),
(56, 'YGO-010', 15.00),
(57, 'YGO-011', 12.00),
(58, 'YGO-012', 13.50),
(59, 'YGO-013', 25.00),
(60, 'YGO-014', 22.00),
(61, 'YGO-015', 27.00),
(62, 'YGO-016', 40.00),
(63, 'YGO-017', 38.00),
(64, 'YGO-018', 42.00),
(65, 'YGO-019', 80.00),
(66, 'YGO-020', 95.00),
(67, 'YGO-021', 90.00),
(68, 'PKM-002', 0.50),
(69, 'PKM-003', 0.70),
(70, 'PKM-004', 0.65),
(71, 'PKM-005', 0.75),
(72, 'PKM-006', 0.90),
(73, 'PKM-007', 3.50),
(74, 'PKM-008', 3.00),
(75, 'PKM-009', 3.20),
(76, 'PKM-010', 15.00),
(77, 'PKM-011', 14.00),
(78, 'PKM-012', 16.00),
(79, 'PKM-013', 25.00),
(80, 'PKM-014', 23.00),
(81, 'PKM-015', 27.00),
(82, 'PKM-016', 40.00),
(83, 'PKM-017', 42.00),
(84, 'PKM-018', 38.00),
(85, 'PKM-019', 70.00),
(86, 'PKM-020', 65.00),
(87, 'PKM-021', 68.00);

-- --------------------------------------------------------

--
-- Struttura della tabella `oggetto_utente`
--

CREATE TABLE `oggetto_utente` (
  `fk_utente` int(11) NOT NULL,
  `fk_oggetto` int(11) NOT NULL,
  `quantita_ogg` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `oggetto_utente`
--

INSERT INTO `oggetto_utente` (`fk_utente`, `fk_oggetto`, `quantita_ogg`) VALUES
(1, 1, 6),
(1, 2, 6),
(1, 48, 1),
(1, 85, 1),
(2, 1, 1),
(2, 2, 1);

-- --------------------------------------------------------

--
-- Struttura della tabella `ordine`
--

CREATE TABLE `ordine` (
  `id_ordine` int(11) NOT NULL,
  `data_ordine` datetime NOT NULL DEFAULT current_timestamp(),
  `tracking` varchar(100) DEFAULT NULL,
  `stato_ordine` tinyint(1) NOT NULL,
  `fk_utente` int(11) NOT NULL,
  `fk_indirizzo` int(11) NOT NULL,
  `fk_carrello` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `ordine`
--

INSERT INTO `ordine` (`id_ordine`, `data_ordine`, `tracking`, `stato_ordine`, `fk_utente`, `fk_indirizzo`, `fk_carrello`) VALUES
(2, '2025-08-10 17:45:30', 'ciaoèiltrackingquesto', 2, 1, 1, NULL),
(3, '2025-08-10 17:56:37', '-', 4, 1, 1, NULL);

-- --------------------------------------------------------

--
-- Struttura della tabella `ordine_log`
--

CREATE TABLE `ordine_log` (
  `id_log` int(11) NOT NULL,
  `fk_ordine` int(11) NOT NULL,
  `stato_precedente` tinyint(1) DEFAULT NULL,
  `stato_nuovo` tinyint(1) NOT NULL,
  `note` text DEFAULT NULL,
  `modificato_da` int(11) DEFAULT NULL,
  `data_modifica` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `ordine_log`
--

INSERT INTO `ordine_log` (`id_log`, `fk_ordine`, `stato_precedente`, `stato_nuovo`, `note`, `modificato_da`, `data_modifica`) VALUES
(1, 2, 0, 1, '', NULL, '2025-08-10 17:46:50'),
(2, 2, 1, 2, '', NULL, '2025-08-10 17:54:40'),
(3, 3, 0, 4, '', NULL, '2025-08-10 17:57:30');

-- --------------------------------------------------------

--
-- Struttura della tabella `punti_utente`
--

CREATE TABLE `punti_utente` (
  `id_punti` int(11) NOT NULL,
  `punti` int(11) NOT NULL DEFAULT 0,
  `livello` int(11) NOT NULL DEFAULT 0,
  `fk_utente` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `rarita`
--

CREATE TABLE `rarita` (
  `id_rarita` int(11) NOT NULL,
  `nome_rarita` varchar(100) NOT NULL,
  `colore` varchar(100) DEFAULT NULL,
  `ordine` int(11) DEFAULT NULL,
  `probabilita` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `rarita`
--

INSERT INTO `rarita` (`id_rarita`, `nome_rarita`, `colore`, `ordine`, `probabilita`) VALUES
(1, 'Comune', '#008000', 1, NULL),
(2, 'Rara', '#87CEEB', 2, NULL),
(3, 'Ultra Rara', '#000000', 3, NULL),
(4, 'Epica', '#A020F0', 4, NULL),
(5, 'Mitica', '#FF0000', 5, NULL),
(6, 'Leggendaria', '#FFD700', 6, NULL);

-- --------------------------------------------------------

--
-- Struttura della tabella `scambio`
--

CREATE TABLE `scambio` (
  `id_scambio` int(11) NOT NULL,
  `data_scambio` datetime NOT NULL DEFAULT current_timestamp(),
  `stato_scambio` varchar(50) NOT NULL,
  `fk_utente` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `scambio_oggetto`
--

CREATE TABLE `scambio_oggetto` (
  `fk_scambio` int(11) NOT NULL,
  `fk_oggetto` int(11) NOT NULL,
  `da_utente` tinyint(1) NOT NULL,
  `quantita_scambio` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `titolo`
--

CREATE TABLE `titolo` (
  `id_titolo` int(11) NOT NULL,
  `nome_titolo` varchar(100) NOT NULL,
  `descrizione_titolo` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `titolo_utente`
--

CREATE TABLE `titolo_utente` (
  `fk_titolo` int(11) NOT NULL,
  `fk_utente` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `utente`
--

CREATE TABLE `utente` (
  `id_utente` int(11) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `cognome` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `password` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `utente`
--

INSERT INTO `utente` (`id_utente`, `nome`, `cognome`, `email`, `telefono`, `password`) VALUES
(1, 'Davide', 'Blasioli', 'db@gmail.com', NULL, '$2y$10$GcEqvT2f6DNgRx39AKAfxOZhHVhPsHfu827tVcun2E7DT38XL4H4K'),
(2, 'Francesco', 'Vampa', 'fv@boxomnia.it', NULL, '$2y$10$NXPIRLr5x50X6Do73ySyNuX/JkeMiGkfM2r24MPkovCvCV64WfRWi'),
(6, 'Admin', 'Box Omnia', 'admin@boxomnia.it', NULL, '$2y$10$GcEqvT2f6DNgRx39AKAfxOZhHVhPsHfu827tVcun2E7DT38XL4H4K');

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `vista_ordini_completi`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `vista_ordini_completi` (
`id_ordine` int(11)
,`data_ordine` datetime
,`tracking` varchar(100)
,`stato_ordine` tinyint(1)
,`stato_nome` varchar(15)
,`id_utente` int(11)
,`cliente_nome` varchar(201)
,`cliente_email` varchar(100)
,`cliente_telefono` varchar(20)
,`totale_ordine` decimal(10,2)
,`quantita_articoli` bigint(20)
,`stato_carrello` enum('attivo','checkout','completato','abbandonato')
,`indirizzo_completo` varchar(351)
,`nazione` varchar(100)
);

-- --------------------------------------------------------

--
-- Struttura stand-in per le viste `vista_statistiche_dashboard`
-- (Vedi sotto per la vista effettiva)
--
CREATE TABLE `vista_statistiche_dashboard` (
`utenti_totali` bigint(21)
,`ordini_oggi` bigint(21)
,`ordini_in_elaborazione` bigint(21)
,`fatturato_mese` decimal(32,2)
,`carrelli_attivi` bigint(21)
,`valore_carrelli_attivi` decimal(32,2)
,`mystery_box_disponibili` bigint(21)
,`oggetti_disponibili` bigint(21)
);

-- --------------------------------------------------------

--
-- Struttura per vista `vista_ordini_completi`
--
DROP TABLE IF EXISTS `vista_ordini_completi`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_ordini_completi`  AS SELECT `o`.`id_ordine` AS `id_ordine`, `o`.`data_ordine` AS `data_ordine`, `o`.`tracking` AS `tracking`, `o`.`stato_ordine` AS `stato_ordine`, CASE WHEN `o`.`stato_ordine` = 0 THEN 'In elaborazione' WHEN `o`.`stato_ordine` = 1 THEN 'Spedito' WHEN `o`.`stato_ordine` = 2 THEN 'Consegnato' WHEN `o`.`stato_ordine` = 3 THEN 'Annullato' WHEN `o`.`stato_ordine` = 4 THEN 'Rimborsato' ELSE 'Sconosciuto' END AS `stato_nome`, `u`.`id_utente` AS `id_utente`, concat(`u`.`nome`,' ',`u`.`cognome`) AS `cliente_nome`, `u`.`email` AS `cliente_email`, `u`.`telefono` AS `cliente_telefono`, `c`.`totale` AS `totale_ordine`, `c`.`quantita` AS `quantita_articoli`, `c`.`stato` AS `stato_carrello`, concat('Via ',`i`.`via`,' ',`i`.`civico`,', ',`i`.`cap`,' ',`i`.`citta`,' (',`i`.`provincia`,')') AS `indirizzo_completo`, `i`.`nazione` AS `nazione` FROM (((`ordine` `o` join `utente` `u` on(`o`.`fk_utente` = `u`.`id_utente`)) left join `carrello` `c` on(`o`.`fk_carrello` = `c`.`id_carrello`)) join `indirizzo_spedizione` `i` on(`o`.`fk_indirizzo` = `i`.`id_indirizzo`)) ;

-- --------------------------------------------------------

--
-- Struttura per vista `vista_statistiche_dashboard`
--
DROP TABLE IF EXISTS `vista_statistiche_dashboard`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `vista_statistiche_dashboard`  AS SELECT (select count(0) from `utente`) AS `utenti_totali`, (select count(0) from `ordine` where cast(`ordine`.`data_ordine` as date) = curdate()) AS `ordini_oggi`, (select count(0) from `ordine` where `ordine`.`stato_ordine` = 0) AS `ordini_in_elaborazione`, (select ifnull(sum(`c`.`totale`),0) from (`ordine` `o` left join `carrello` `c` on(`o`.`fk_carrello` = `c`.`id_carrello`)) where date_format(`o`.`data_ordine`,'%Y-%m') = date_format(current_timestamp(),'%Y-%m')) AS `fatturato_mese`, (select count(0) from `carrello` where `carrello`.`stato` = 'attivo') AS `carrelli_attivi`, (select ifnull(sum(`carrello`.`totale`),0) from `carrello` where `carrello`.`stato` = 'attivo') AS `valore_carrelli_attivi`, (select count(0) from `mystery_box` where `mystery_box`.`quantita_box` > 0) AS `mystery_box_disponibili`, (select count(0) from `oggetto` where `oggetto`.`quant_oggetto` > 0 or `oggetto`.`quant_oggetto` is null) AS `oggetti_disponibili` ;

--
-- Indici per le tabelle scaricate
--

--
-- Indici per le tabelle `admin`
--
ALTER TABLE `admin`
  ADD PRIMARY KEY (`id_admin`),
  ADD UNIQUE KEY `fk_utente_unique` (`fk_utente`),
  ADD KEY `fk_utente_admin` (`fk_utente`),
  ADD KEY `fk_creato_da_admin` (`creato_da`);

--
-- Indici per le tabelle `box_oggetto`
--
ALTER TABLE `box_oggetto`
  ADD PRIMARY KEY (`fk_box`,`fk_oggetto`),
  ADD KEY `fk_oggetto_box_oggetto` (`fk_oggetto`);

--
-- Indici per le tabelle `carrello`
--
ALTER TABLE `carrello`
  ADD PRIMARY KEY (`id_carrello`),
  ADD KEY `fk_utente_carrello` (`fk_utente`),
  ADD KEY `fk_mystery_box_carrello` (`fk_mystery_box`),
  ADD KEY `fk_oggetto_carrello` (`fk_oggetto`),
  ADD KEY `idx_utente_stato` (`fk_utente`,`stato`);

--
-- Indici per le tabelle `carrello_salvato`
--
ALTER TABLE `carrello_salvato`
  ADD PRIMARY KEY (`id_salvato`),
  ADD KEY `fk_utente_salvato` (`fk_utente`),
  ADD KEY `fk_mystery_box_salvato` (`fk_mystery_box`),
  ADD KEY `fk_oggetto_salvato` (`fk_oggetto`);

--
-- Indici per le tabelle `carrello_utente`
--
ALTER TABLE `carrello_utente`
  ADD PRIMARY KEY (`fk_carrello`,`fk_utente`) USING BTREE,
  ADD KEY `fk_mystery_box` (`fk_utente`);

--
-- Indici per le tabelle `categoria_oggetto`
--
ALTER TABLE `categoria_oggetto`
  ADD PRIMARY KEY (`id_categoria`);

--
-- Indici per le tabelle `classifica`
--
ALTER TABLE `classifica`
  ADD PRIMARY KEY (`id_classifica`);

--
-- Indici per le tabelle `fattura`
--
ALTER TABLE `fattura`
  ADD PRIMARY KEY (`id_fattura`),
  ADD KEY `fk_utente_fatture` (`fk_utente`);

--
-- Indici per le tabelle `immagine`
--
ALTER TABLE `immagine`
  ADD PRIMARY KEY (`id_immagine`),
  ADD KEY `fk_oggetto_immagine` (`fk_oggetto`),
  ADD KEY `fk_mystery_box_immagine` (`fk_mystery_box`);

--
-- Indici per le tabelle `indirizzo_spedizione`
--
ALTER TABLE `indirizzo_spedizione`
  ADD PRIMARY KEY (`id_indirizzo`),
  ADD KEY `fk_utente_indirizzo_spedizione` (`fk_utente`);

--
-- Indici per le tabelle `info_ordine`
--
ALTER TABLE `info_ordine`
  ADD PRIMARY KEY (`fk_ordine`,`fk_box`),
  ADD KEY `fk_box_info_ordine` (`fk_box`);

--
-- Indici per le tabelle `mystery_box`
--
ALTER TABLE `mystery_box`
  ADD PRIMARY KEY (`id_box`),
  ADD KEY `fk_rarita_mystery_box` (`fk_rarita`),
  ADD KEY `fk_categoria_oggetto_mystery_box` (`fk_categoria_oggetto`);

--
-- Indici per le tabelle `novita_box`
--
ALTER TABLE `novita_box`
  ADD PRIMARY KEY (`fk_mystery_box`);

--
-- Indici per le tabelle `novita_oggetto`
--
ALTER TABLE `novita_oggetto`
  ADD PRIMARY KEY (`fk_oggetto`);

--
-- Indici per le tabelle `oggetto`
--
ALTER TABLE `oggetto`
  ADD PRIMARY KEY (`id_oggetto`),
  ADD KEY `fk_categoria_oggetto_oggetto` (`fk_categoria_oggetto`),
  ADD KEY `fk_rarita_oggetto` (`fk_rarita`);

--
-- Indici per le tabelle `oggetto_collezione`
--
ALTER TABLE `oggetto_collezione`
  ADD PRIMARY KEY (`fk_oggetto`);

--
-- Indici per le tabelle `oggetto_utente`
--
ALTER TABLE `oggetto_utente`
  ADD PRIMARY KEY (`fk_utente`,`fk_oggetto`),
  ADD KEY `fk_oggetto_oggetto_utente` (`fk_oggetto`);

--
-- Indici per le tabelle `ordine`
--
ALTER TABLE `ordine`
  ADD PRIMARY KEY (`id_ordine`),
  ADD KEY `fk_utente_ordine` (`fk_utente`),
  ADD KEY `fk_indirizzo_spedizione_ordine` (`fk_indirizzo`),
  ADD KEY `fk_carrello_ordine` (`fk_carrello`);

--
-- Indici per le tabelle `ordine_log`
--
ALTER TABLE `ordine_log`
  ADD PRIMARY KEY (`id_log`),
  ADD KEY `fk_ordine_log` (`fk_ordine`),
  ADD KEY `fk_admin_log` (`modificato_da`);

--
-- Indici per le tabelle `punti_utente`
--
ALTER TABLE `punti_utente`
  ADD PRIMARY KEY (`id_punti`),
  ADD KEY `fk_utente_punti_utente` (`fk_utente`);

--
-- Indici per le tabelle `rarita`
--
ALTER TABLE `rarita`
  ADD PRIMARY KEY (`id_rarita`);

--
-- Indici per le tabelle `scambio`
--
ALTER TABLE `scambio`
  ADD PRIMARY KEY (`id_scambio`),
  ADD KEY `fk_utente_scambio` (`fk_utente`);

--
-- Indici per le tabelle `scambio_oggetto`
--
ALTER TABLE `scambio_oggetto`
  ADD PRIMARY KEY (`fk_scambio`,`fk_oggetto`),
  ADD KEY `fk_oggetto_scambio_oggetto` (`fk_oggetto`);

--
-- Indici per le tabelle `titolo`
--
ALTER TABLE `titolo`
  ADD PRIMARY KEY (`id_titolo`);

--
-- Indici per le tabelle `titolo_utente`
--
ALTER TABLE `titolo_utente`
  ADD PRIMARY KEY (`fk_titolo`,`fk_utente`),
  ADD KEY `fk_utente_titolo_utente` (`fk_utente`);

--
-- Indici per le tabelle `utente`
--
ALTER TABLE `utente`
  ADD PRIMARY KEY (`id_utente`);

--
-- AUTO_INCREMENT per le tabelle scaricate
--

--
-- AUTO_INCREMENT per la tabella `admin`
--
ALTER TABLE `admin`
  MODIFY `id_admin` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT per la tabella `carrello`
--
ALTER TABLE `carrello`
  MODIFY `id_carrello` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT per la tabella `carrello_salvato`
--
ALTER TABLE `carrello_salvato`
  MODIFY `id_salvato` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `categoria_oggetto`
--
ALTER TABLE `categoria_oggetto`
  MODIFY `id_categoria` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT per la tabella `classifica`
--
ALTER TABLE `classifica`
  MODIFY `id_classifica` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `fattura`
--
ALTER TABLE `fattura`
  MODIFY `id_fattura` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `immagine`
--
ALTER TABLE `immagine`
  MODIFY `id_immagine` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=146;

--
-- AUTO_INCREMENT per la tabella `indirizzo_spedizione`
--
ALTER TABLE `indirizzo_spedizione`
  MODIFY `id_indirizzo` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT per la tabella `mystery_box`
--
ALTER TABLE `mystery_box`
  MODIFY `id_box` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT per la tabella `oggetto`
--
ALTER TABLE `oggetto`
  MODIFY `id_oggetto` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=106;

--
-- AUTO_INCREMENT per la tabella `ordine`
--
ALTER TABLE `ordine`
  MODIFY `id_ordine` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT per la tabella `ordine_log`
--
ALTER TABLE `ordine_log`
  MODIFY `id_log` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT per la tabella `punti_utente`
--
ALTER TABLE `punti_utente`
  MODIFY `id_punti` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `rarita`
--
ALTER TABLE `rarita`
  MODIFY `id_rarita` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT per la tabella `scambio`
--
ALTER TABLE `scambio`
  MODIFY `id_scambio` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `titolo`
--
ALTER TABLE `titolo`
  MODIFY `id_titolo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `utente`
--
ALTER TABLE `utente`
  MODIFY `id_utente` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Limiti per le tabelle scaricate
--

--
-- Limiti per la tabella `admin`
--
ALTER TABLE `admin`
  ADD CONSTRAINT `fk_creato_da_admin` FOREIGN KEY (`creato_da`) REFERENCES `utente` (`id_utente`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_utente_admin` FOREIGN KEY (`fk_utente`) REFERENCES `utente` (`id_utente`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limiti per la tabella `box_oggetto`
--
ALTER TABLE `box_oggetto`
  ADD CONSTRAINT `fk_mystery_box_box_oggetto` FOREIGN KEY (`fk_box`) REFERENCES `mystery_box` (`id_box`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_oggetto_box_oggetto` FOREIGN KEY (`fk_oggetto`) REFERENCES `oggetto` (`id_oggetto`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limiti per la tabella `carrello`
--
ALTER TABLE `carrello`
  ADD CONSTRAINT `fk_mystery_box_carrello` FOREIGN KEY (`fk_mystery_box`) REFERENCES `mystery_box` (`id_box`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_oggetto_carrello` FOREIGN KEY (`fk_oggetto`) REFERENCES `oggetto` (`id_oggetto`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_utente_carrello` FOREIGN KEY (`fk_utente`) REFERENCES `utente` (`id_utente`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limiti per la tabella `carrello_salvato`
--
ALTER TABLE `carrello_salvato`
  ADD CONSTRAINT `fk_mystery_box_carrello_salvato` FOREIGN KEY (`fk_mystery_box`) REFERENCES `mystery_box` (`id_box`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_oggetto_carrello_salvato` FOREIGN KEY (`fk_oggetto`) REFERENCES `oggetto` (`id_oggetto`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_utente_carrello_salvato` FOREIGN KEY (`fk_utente`) REFERENCES `utente` (`id_utente`) ON DELETE CASCADE;

--
-- Limiti per la tabella `carrello_utente`
--
ALTER TABLE `carrello_utente`
  ADD CONSTRAINT `fk_carrello_carrello_box` FOREIGN KEY (`fk_carrello`) REFERENCES `carrello` (`id_carrello`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mystery_box` FOREIGN KEY (`fk_utente`) REFERENCES `utente` (`id_utente`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limiti per la tabella `fattura`
--
ALTER TABLE `fattura`
  ADD CONSTRAINT `fk_utente_fatture` FOREIGN KEY (`fk_utente`) REFERENCES `utente` (`id_utente`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limiti per la tabella `immagine`
--
ALTER TABLE `immagine`
  ADD CONSTRAINT `fk_mystery_box_immagine` FOREIGN KEY (`fk_mystery_box`) REFERENCES `mystery_box` (`id_box`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_oggetto_immagine` FOREIGN KEY (`fk_oggetto`) REFERENCES `oggetto` (`id_oggetto`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limiti per la tabella `indirizzo_spedizione`
--
ALTER TABLE `indirizzo_spedizione`
  ADD CONSTRAINT `fk_utente_indirizzo_spedizione` FOREIGN KEY (`fk_utente`) REFERENCES `utente` (`id_utente`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limiti per la tabella `info_ordine`
--
ALTER TABLE `info_ordine`
  ADD CONSTRAINT `fk_box_info_ordine` FOREIGN KEY (`fk_box`) REFERENCES `mystery_box` (`id_box`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ordine_info_ordine` FOREIGN KEY (`fk_ordine`) REFERENCES `ordine` (`id_ordine`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limiti per la tabella `mystery_box`
--
ALTER TABLE `mystery_box`
  ADD CONSTRAINT `fk_categoria_oggetto_mystery_box` FOREIGN KEY (`fk_categoria_oggetto`) REFERENCES `categoria_oggetto` (`id_categoria`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rarita_mystery_box` FOREIGN KEY (`fk_rarita`) REFERENCES `rarita` (`id_rarita`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limiti per la tabella `novita_box`
--
ALTER TABLE `novita_box`
  ADD CONSTRAINT `fk_mystery_box_novita_box` FOREIGN KEY (`fk_mystery_box`) REFERENCES `mystery_box` (`id_box`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limiti per la tabella `novita_oggetto`
--
ALTER TABLE `novita_oggetto`
  ADD CONSTRAINT `fk_oggetto_novita_oggetto` FOREIGN KEY (`fk_oggetto`) REFERENCES `oggetto` (`id_oggetto`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limiti per la tabella `oggetto`
--
ALTER TABLE `oggetto`
  ADD CONSTRAINT `fk_categoria_oggetto_oggetto` FOREIGN KEY (`fk_categoria_oggetto`) REFERENCES `categoria_oggetto` (`id_categoria`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_rarita_oggetto` FOREIGN KEY (`fk_rarita`) REFERENCES `rarita` (`id_rarita`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limiti per la tabella `oggetto_collezione`
--
ALTER TABLE `oggetto_collezione`
  ADD CONSTRAINT `fk_oggetto_oggetto_collezione` FOREIGN KEY (`fk_oggetto`) REFERENCES `oggetto` (`id_oggetto`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limiti per la tabella `oggetto_utente`
--
ALTER TABLE `oggetto_utente`
  ADD CONSTRAINT `fk_oggetto_oggetto_utente` FOREIGN KEY (`fk_oggetto`) REFERENCES `oggetto` (`id_oggetto`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_utente_oggetto_utente` FOREIGN KEY (`fk_utente`) REFERENCES `utente` (`id_utente`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limiti per la tabella `ordine`
--
ALTER TABLE `ordine`
  ADD CONSTRAINT `fk_carrello_ordine` FOREIGN KEY (`fk_carrello`) REFERENCES `carrello` (`id_carrello`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_indirizzo_spedizione_ordine` FOREIGN KEY (`fk_indirizzo`) REFERENCES `indirizzo_spedizione` (`id_indirizzo`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_utente_ordine` FOREIGN KEY (`fk_utente`) REFERENCES `utente` (`id_utente`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limiti per la tabella `ordine_log`
--
ALTER TABLE `ordine_log`
  ADD CONSTRAINT `fk_admin_ordine_log` FOREIGN KEY (`modificato_da`) REFERENCES `admin` (`id_admin`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ordine_ordine_log` FOREIGN KEY (`fk_ordine`) REFERENCES `ordine` (`id_ordine`) ON DELETE CASCADE;

--
-- Limiti per la tabella `punti_utente`
--
ALTER TABLE `punti_utente`
  ADD CONSTRAINT `fk_utente_punti_utente` FOREIGN KEY (`fk_utente`) REFERENCES `utente` (`id_utente`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limiti per la tabella `scambio`
--
ALTER TABLE `scambio`
  ADD CONSTRAINT `fk_utente_scambio` FOREIGN KEY (`fk_utente`) REFERENCES `utente` (`id_utente`);

--
-- Limiti per la tabella `scambio_oggetto`
--
ALTER TABLE `scambio_oggetto`
  ADD CONSTRAINT `fk_oggetto_scambio_oggetto` FOREIGN KEY (`fk_oggetto`) REFERENCES `oggetto` (`id_oggetto`),
  ADD CONSTRAINT `fk_scambio_scambio_oggetto` FOREIGN KEY (`fk_scambio`) REFERENCES `scambio` (`id_scambio`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limiti per la tabella `titolo_utente`
--
ALTER TABLE `titolo_utente`
  ADD CONSTRAINT `fk_titolo_titolo_utente` FOREIGN KEY (`fk_titolo`) REFERENCES `titolo` (`id_titolo`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_utente_titolo_utente` FOREIGN KEY (`fk_utente`) REFERENCES `utente` (`id_utente`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

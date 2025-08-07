-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:8889
-- Creato il: Ago 07, 2025 alle 10:03
-- Versione del server: 8.0.40
-- Versione PHP: 8.3.14

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

-- --------------------------------------------------------

--
-- Struttura della tabella `admin`
--

CREATE TABLE `admin` (
  `id_admin` int NOT NULL,
  `fk_utente` int NOT NULL,
  `livello_admin` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'admin',
  `data_creazione` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `creato_da` int DEFAULT NULL
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
  `fk_box` int NOT NULL,
  `fk_oggetto` int NOT NULL,
  `probabilita` float NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `carrello`
--

CREATE TABLE `carrello` (
  `id_carrello` int NOT NULL,
  `totale` decimal(10,0) NOT NULL,
  `fk_utente` int NOT NULL,
  `data_creazione` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `quantita` bigint NOT NULL,
  `fk_mystery_box` int DEFAULT NULL,
  `fk_oggetto` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `carrello_utente`
--

CREATE TABLE `carrello_utente` (
  `fk_carrello` int NOT NULL,
  `fk_utente` int NOT NULL,
  `quantita` int NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `categoria_oggetto`
--

CREATE TABLE `categoria_oggetto` (
  `id_categoria` int NOT NULL,
  `nome_categoria` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_oggetto` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `categoria_oggetto`
--

INSERT INTO `categoria_oggetto` (`id_categoria`, `nome_categoria`, `tipo_oggetto`) VALUES
(1, 'Yu-Gi-Oh!', 'Carta Singola'),
(2, 'Pokémon', 'Carta Singola'),
(3, 'Universale', 'Proteggicarte'),
(4, 'Universale', 'Plance di gioco'),
(5, 'Universale', 'Scatole porta mazzi'),
(6, 'Universale', 'Porta carte'),
(7, 'Pokémon', 'Mystery Box');

-- --------------------------------------------------------

--
-- Struttura della tabella `classifica`
--

CREATE TABLE `classifica` (
  `id_classifica` int NOT NULL,
  `nome_classifica` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo_classifica` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `desc_classifica` text COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `fattura`
--

CREATE TABLE `fattura` (
  `id_fattura` int NOT NULL,
  `tipo` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `totale_fattura` bigint NOT NULL,
  `data_emissione` datetime NOT NULL,
  `fk_utente` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `immagine`
--

CREATE TABLE `immagine` (
  `id_immagine` int NOT NULL,
  `nome_img` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `descrizione_img` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `dimensione` int DEFAULT NULL,
  `fk_oggetto` int DEFAULT NULL,
  `fk_mystery_box` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `immagine`
--

INSERT INTO `immagine` (`id_immagine`, `nome_img`, `descrizione_img`, `dimensione`, `fk_oggetto`, `fk_mystery_box`) VALUES
(1, 'mago_nero.jpg', NULL, NULL, 1, NULL),
(2, 'plancia_arancione.png', NULL, NULL, 3, NULL);

-- --------------------------------------------------------

--
-- Struttura della tabella `indirizzo_spedizione`
--

CREATE TABLE `indirizzo_spedizione` (
  `id_indirizzo` int NOT NULL,
  `via` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `civico` bigint NOT NULL,
  `cap` bigint NOT NULL,
  `citta` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nazione` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `provincia` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fk_utente` int NOT NULL
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
  `fk_ordine` int NOT NULL,
  `fk_box` int NOT NULL,
  `quantita_ordine` int NOT NULL,
  `totale_ordine` decimal(10,0) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `mystery_box`
--

CREATE TABLE `mystery_box` (
  `id_box` int NOT NULL,
  `nome_box` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `desc_box` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `prezzo_box` decimal(10,0) NOT NULL,
  `quantita_box` int NOT NULL,
  `fk_rarita` int NOT NULL,
  `fk_categoria_oggetto` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `mystery_box`
--

INSERT INTO `mystery_box` (`id_box`, `nome_box`, `desc_box`, `prezzo_box`, `quantita_box`, `fk_rarita`, `fk_categoria_oggetto`) VALUES
(2, 'PokéBox - Raro', 'Mystery Box che potrebbe contenere carte singole, bustine e/o starter pack di rarità: rara', 20, 2, 2, 7);

-- --------------------------------------------------------

--
-- Struttura della tabella `novita_box`
--

CREATE TABLE `novita_box` (
  `fk_box` int NOT NULL,
  `data_novita` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `oggetto`
--

CREATE TABLE `oggetto` (
  `id_oggetto` int NOT NULL,
  `nome_oggetto` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `desc_oggetto` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `prezzo_oggetto` decimal(10,0) DEFAULT NULL,
  `quant_oggetto` int DEFAULT NULL,
  `fk_categoria_oggetto` int NOT NULL,
  `fk_rarita` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `oggetto`
--

INSERT INTO `oggetto` (`id_oggetto`, `nome_oggetto`, `desc_oggetto`, `prezzo_oggetto`, `quant_oggetto`, `fk_categoria_oggetto`, `fk_rarita`) VALUES
(1, 'Mago Nero', 'Mostro di tipo Incantesimo', NULL, NULL, 1, 3),
(2, 'Pikachu', 'Pokémon di tipo Elettro', NULL, NULL, 2, 1),
(3, 'Plancia di gioco', 'Colore: arancione', 15, NULL, 4, NULL);

-- --------------------------------------------------------

--
-- Struttura della tabella `oggetto_collezione`
--

CREATE TABLE `oggetto_collezione` (
  `fk_oggetto` int NOT NULL,
  `numero_carta` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valore_stimato` decimal(10,0) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `oggetto_collezione`
--

INSERT INTO `oggetto_collezione` (`fk_oggetto`, `numero_carta`, `valore_stimato`) VALUES
(1, 'YGO-001', 10),
(2, 'POK-001', 1);

-- --------------------------------------------------------

--
-- Struttura della tabella `oggetto_utente`
--

CREATE TABLE `oggetto_utente` (
  `fk_utente` int NOT NULL,
  `fk_oggetto` int NOT NULL,
  `quantita_ogg` int NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `oggetto_utente`
--

INSERT INTO `oggetto_utente` (`fk_utente`, `fk_oggetto`, `quantita_ogg`) VALUES
(1, 1, 1),
(1, 2, 3),
(2, 1, 1),
(2, 2, 1);

-- --------------------------------------------------------

--
-- Struttura della tabella `ordine`
--

CREATE TABLE `ordine` (
  `id_ordine` int NOT NULL,
  `data_ordine` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `tracking` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `stato_ordine` tinyint(1) NOT NULL,
  `fk_utente` int NOT NULL,
  `fk_indirizzo` int NOT NULL,
  `fk_carrello` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `punti_utente`
--

CREATE TABLE `punti_utente` (
  `id_punti` int NOT NULL,
  `punti` int NOT NULL DEFAULT '0',
  `livello` int NOT NULL DEFAULT '0',
  `fk_utente` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `rarita`
--

CREATE TABLE `rarita` (
  `id_rarita` int NOT NULL,
  `nome_rarita` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `colore` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ordine` int DEFAULT NULL,
  `probabilita` float DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `rarita`
--

INSERT INTO `rarita` (`id_rarita`, `nome_rarita`, `colore`, `ordine`, `probabilita`) VALUES
(1, 'Comune', '#008000', 1, NULL),
(2, 'Rara', '#ADD8E6', 2, NULL),
(3, 'Ultra Rara', '#000000', 3, NULL);

-- --------------------------------------------------------

--
-- Struttura della tabella `scambio`
--

CREATE TABLE `scambio` (
  `id_scambio` int NOT NULL,
  `data_scambio` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `stato_scambio` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fk_utente` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `scambio_oggetto`
--

CREATE TABLE `scambio_oggetto` (
  `fk_scambio` int NOT NULL,
  `fk_oggetto` int NOT NULL,
  `da_utente` tinyint(1) NOT NULL,
  `quantita_scambio` int NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `titolo`
--

CREATE TABLE `titolo` (
  `id_titolo` int NOT NULL,
  `nome_titolo` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descrizione_titolo` text COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `titolo_utente`
--

CREATE TABLE `titolo_utente` (
  `fk_titolo` int NOT NULL,
  `fk_utente` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `utente`
--

CREATE TABLE `utente` (
  `id_utente` int NOT NULL,
  `nome` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cognome` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dump dei dati per la tabella `utente`
--

INSERT INTO `utente` (`id_utente`, `nome`, `cognome`, `email`, `password`) VALUES
(1, 'Davide', 'Blasioli', 'db@gmail.com', '$2y$10$GcEqvT2f6DNgRx39AKAfxOZhHVhPsHfu827tVcun2E7DT38XL4H4K'),
(2, 'Francesco', 'Vampa', 'fv@boxomnia.it', '$2y$10$NXPIRLr5x50X6Do73ySyNuX/JkeMiGkfM2r24MPkovCvCV64WfRWi'),
(6, 'Admin', 'Box Omnia', 'admin@boxomnia.it', '$2y$10$GcEqvT2f6DNgRx39AKAfxOZhHVhPsHfu827tVcun2E7DT38XL4H4K');

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
  ADD KEY `fk_oggetto_carrello` (`fk_oggetto`);

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
  ADD PRIMARY KEY (`fk_box`);

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
  MODIFY `id_admin` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT per la tabella `carrello`
--
ALTER TABLE `carrello`
  MODIFY `id_carrello` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT per la tabella `categoria_oggetto`
--
ALTER TABLE `categoria_oggetto`
  MODIFY `id_categoria` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT per la tabella `classifica`
--
ALTER TABLE `classifica`
  MODIFY `id_classifica` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `fattura`
--
ALTER TABLE `fattura`
  MODIFY `id_fattura` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `immagine`
--
ALTER TABLE `immagine`
  MODIFY `id_immagine` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT per la tabella `indirizzo_spedizione`
--
ALTER TABLE `indirizzo_spedizione`
  MODIFY `id_indirizzo` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT per la tabella `mystery_box`
--
ALTER TABLE `mystery_box`
  MODIFY `id_box` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT per la tabella `oggetto`
--
ALTER TABLE `oggetto`
  MODIFY `id_oggetto` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT per la tabella `oggetto_collezione`
--
ALTER TABLE `oggetto_collezione`
  MODIFY `fk_oggetto` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT per la tabella `ordine`
--
ALTER TABLE `ordine`
  MODIFY `id_ordine` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT per la tabella `punti_utente`
--
ALTER TABLE `punti_utente`
  MODIFY `id_punti` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `rarita`
--
ALTER TABLE `rarita`
  MODIFY `id_rarita` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT per la tabella `scambio`
--
ALTER TABLE `scambio`
  MODIFY `id_scambio` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `titolo`
--
ALTER TABLE `titolo`
  MODIFY `id_titolo` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `utente`
--
ALTER TABLE `utente`
  MODIFY `id_utente` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

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
  ADD CONSTRAINT `fk_box_novita_box` FOREIGN KEY (`fk_box`) REFERENCES `mystery_box` (`id_box`) ON DELETE CASCADE ON UPDATE CASCADE;

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
  ADD CONSTRAINT `fk_carrello_ordine` FOREIGN KEY (`fk_carrello`) REFERENCES `carrello` (`id_carrello`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_indirizzo_spedizione_ordine` FOREIGN KEY (`fk_indirizzo`) REFERENCES `indirizzo_spedizione` (`id_indirizzo`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_utente_ordine` FOREIGN KEY (`fk_utente`) REFERENCES `utente` (`id_utente`) ON DELETE CASCADE ON UPDATE CASCADE;

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

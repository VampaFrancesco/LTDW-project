-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Creato il: Ago 02, 2025 alle 11:45
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

-- --------------------------------------------------------

--
-- Struttura della tabella `box_oggetto`
--

CREATE TABLE `box_oggetto` (
  `fk_box` int(11) NOT NULL,
  `fk_oggetto` int(11) NOT NULL,
  `probabilita` float NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `carrello`
--

CREATE TABLE `carrello` (
  `id_carrello` int(11) NOT NULL,
  `totale_box` int(11) NOT NULL,
  `fk_utente` int(11) NOT NULL,
  `data_creazione` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `carrello_box`
--

CREATE TABLE `carrello_box` (
  `fk_carrello` int(11) NOT NULL,
  `fk_box` int(11) NOT NULL,
  `quantita` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `categoria_oggetto`
--

CREATE TABLE `categoria_oggetto` (
  `id_categoria` int(11) NOT NULL,
  `nome_categoria` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
-- Struttura della tabella `collezione`
--

CREATE TABLE `collezione` (
  `id_collezione` int(11) NOT NULL,
  `nome_collezione` varchar(100) NOT NULL,
  `desc_collezione` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `fattura`
--

CREATE TABLE `fattura` (
  `id_fattura` int(11) NOT NULL,
  `tipo` varchar(100) NOT NULL,
  `totale_fattura` bigint(20) NOT NULL,
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
  `fk_oggetto` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- --------------------------------------------------------

--
-- Struttura della tabella `info_ordine`
--

CREATE TABLE `info_ordine` (
  `fk_ordine` int(11) NOT NULL,
  `fk_box` int(11) NOT NULL,
  `quantita_ordine` int(11) NOT NULL,
  `totale_ordine` decimal(10,0) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `mystery_box`
--

CREATE TABLE `mystery_box` (
  `id_box` int(11) NOT NULL,
  `nome_box` varchar(100) NOT NULL,
  `desc_box` text NOT NULL,
  `prezzo_box` decimal(10,0) NOT NULL,
  `quantita_box` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `novita_box`
--

CREATE TABLE `novita_box` (
  `fk_box` int(11) NOT NULL,
  `data_novita` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `oggetto`
--

CREATE TABLE `oggetto` (
  `id_oggetto` int(11) NOT NULL,
  `nome_oggetto` varchar(100) NOT NULL,
  `desc_oggetto` text NOT NULL,
  `valore_stimato` decimal(10,0) NOT NULL,
  `fk_categoria_oggetto` int(11) NOT NULL,
  `fk_rarita` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `oggetto_collezione`
--

CREATE TABLE `oggetto_collezione` (
  `fk_oggetto` int(11) NOT NULL,
  `fk_collezione` int(11) NOT NULL,
  `numero_carta` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `oggetto_utente`
--

CREATE TABLE `oggetto_utente` (
  `fk_utente` int(11) NOT NULL,
  `fk_oggetto` int(11) NOT NULL,
  `quantita_ogg` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `fk_indirizzo` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Struttura della tabella `punti_utente`
--

CREATE TABLE `punti_utente` (
  `id_titolo` int(11) NOT NULL,
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
  `colore` varchar(100) NOT NULL,
  `ordine` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `password` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indici per le tabelle scaricate
--

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
  ADD KEY `fk_utente_carrello` (`fk_utente`);

--
-- Indici per le tabelle `carrello_box`
--
ALTER TABLE `carrello_box`
  ADD PRIMARY KEY (`fk_carrello`,`fk_box`),
  ADD KEY `fk_mystery_box` (`fk_box`);

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
-- Indici per le tabelle `collezione`
--
ALTER TABLE `collezione`
  ADD PRIMARY KEY (`id_collezione`);

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
  ADD KEY `fk_oggetto_immagine` (`fk_oggetto`);

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
  ADD PRIMARY KEY (`id_box`);

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
  ADD PRIMARY KEY (`fk_oggetto`,`fk_collezione`),
  ADD KEY `fk_collezione_oggetto_collezione` (`fk_collezione`);

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
  ADD KEY `fk_indirizzo_spedizione_ordine` (`fk_indirizzo`);

--
-- Indici per le tabelle `punti_utente`
--
ALTER TABLE `punti_utente`
  ADD PRIMARY KEY (`id_titolo`),
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
-- AUTO_INCREMENT per la tabella `carrello`
--
ALTER TABLE `carrello`
  MODIFY `id_carrello` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `categoria_oggetto`
--
ALTER TABLE `categoria_oggetto`
  MODIFY `id_categoria` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `classifica`
--
ALTER TABLE `classifica`
  MODIFY `id_classifica` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `collezione`
--
ALTER TABLE `collezione`
  MODIFY `id_collezione` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `fattura`
--
ALTER TABLE `fattura`
  MODIFY `id_fattura` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `immagine`
--
ALTER TABLE `immagine`
  MODIFY `id_immagine` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `indirizzo_spedizione`
--
ALTER TABLE `indirizzo_spedizione`
  MODIFY `id_indirizzo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `mystery_box`
--
ALTER TABLE `mystery_box`
  MODIFY `id_box` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `oggetto`
--
ALTER TABLE `oggetto`
  MODIFY `id_oggetto` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `ordine`
--
ALTER TABLE `ordine`
  MODIFY `id_ordine` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `punti_utente`
--
ALTER TABLE `punti_utente`
  MODIFY `id_titolo` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT per la tabella `rarita`
--
ALTER TABLE `rarita`
  MODIFY `id_rarita` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `id_utente` int(11) NOT NULL AUTO_INCREMENT;

--
-- Limiti per le tabelle scaricate
--

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
  ADD CONSTRAINT `fk_utente_carrello` FOREIGN KEY (`fk_utente`) REFERENCES `utente` (`id_utente`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limiti per la tabella `carrello_box`
--
ALTER TABLE `carrello_box`
  ADD CONSTRAINT `fk_carrello_carrello_box` FOREIGN KEY (`fk_carrello`) REFERENCES `carrello` (`id_carrello`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mystery_box` FOREIGN KEY (`fk_box`) REFERENCES `mystery_box` (`id_box`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limiti per la tabella `fattura`
--
ALTER TABLE `fattura`
  ADD CONSTRAINT `fk_utente_fatture` FOREIGN KEY (`fk_utente`) REFERENCES `utente` (`id_utente`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Limiti per la tabella `immagine`
--
ALTER TABLE `immagine`
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
  ADD CONSTRAINT `fk_collezione_oggetto_collezione` FOREIGN KEY (`fk_collezione`) REFERENCES `collezione` (`id_collezione`),
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

-- phpMyAdmin SQL Dump
-- version 3.4.11.1deb2
-- http://www.phpmyadmin.net
--
-- Host: localhost
-- Erstellungszeit: 20. Apr 2014 um 10:39
-- Server Version: 5.5.35
-- PHP-Version: 5.4.4-14+deb7u8

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

--
-- Datenbank: `strpl`
--

-- --------------------------------------------------------

--
-- Tabellenstruktur für Tabelle `events`
--

CREATE TABLE IF NOT EXISTS `events` (
  `event_id` int(11) NOT NULL AUTO_INCREMENT,
  `event_name` varchar(127) NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `details` text NOT NULL,
  PRIMARY KEY (`event_id`)
) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=119 ;

--
-- Daten für Tabelle `events`
--

INSERT INTO `events` (`event_id`, `event_name`, `start_date`, `end_date`, `details`) VALUES
(118, 'Neue Sendung - Moderator', '2014-05-17 16:10:00', '2014-05-17 19:20:00', ''),
(117, 'Neue Sendung - Moderator', '2014-05-13 17:35:00', '2014-05-13 22:15:00', ''),
(112, 'Neue Sendung - Moderator', '2014-04-19 17:00:00', '2014-04-19 20:00:00', 'Hier kommt eine Beschreibung der Sendung hin...'),
(113, 'Neue Sendung - Moderator', '2014-04-19 20:00:00', '2014-04-19 22:20:00', ''),
(114, 'Neue Sendung - Moderator', '2014-04-18 18:50:00', '2014-04-18 22:15:00', ''),
(115, 'Neue Sendung - Moderator', '2014-04-20 14:45:00', '2014-04-20 16:55:00', ''),
(116, 'Neue Sendung - Moderator', '2014-06-05 17:50:00', '2014-06-05 21:45:00', '');

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

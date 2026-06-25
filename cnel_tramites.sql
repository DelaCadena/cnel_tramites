-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Versión del servidor:         8.4.3 - MySQL Community Server - GPL
-- SO del servidor:              Win64
-- HeidiSQL Versión:             12.8.0.6908
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Volcando estructura de base de datos para cnel_tramites
CREATE DATABASE IF NOT EXISTS `cnel_tramites` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci */ /*!80016 DEFAULT ENCRYPTION='N' */;
USE `cnel_tramites`;

-- Volcando estructura para tabla cnel_tramites.revisiones
CREATE TABLE IF NOT EXISTS `revisiones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tramite_id` int NOT NULL,
  `usuario_id` int NOT NULL,
  `observaciones` text,
  `estado_anterior` enum('pendiente','revision','aprobado','rechazado') DEFAULT NULL,
  `estado_nuevo` enum('pendiente','revision','aprobado','rechazado') DEFAULT NULL,
  `fecha_revision` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tramite_id` (`tramite_id`),
  KEY `usuario_id` (`usuario_id`),
  CONSTRAINT `revisiones_ibfk_1` FOREIGN KEY (`tramite_id`) REFERENCES `tramites` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `revisiones_ibfk_2` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=52 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla cnel_tramites.servicios
CREATE TABLE IF NOT EXISTS `servicios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `encargado_id` int NOT NULL,
  `estado` enum('activo','inactivo') DEFAULT 'activo',
  PRIMARY KEY (`id`),
  KEY `encargado_id` (`encargado_id`),
  CONSTRAINT `servicios_ibfk_1` FOREIGN KEY (`encargado_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla cnel_tramites.tramites
CREATE TABLE IF NOT EXISTS `tramites` (
  `id` int NOT NULL AUTO_INCREMENT,
  `numero_tramite` varchar(20) NOT NULL,
  `tipo` enum('extension_red','ferum') NOT NULL,
  `solicitante` varchar(200) NOT NULL,
  `cedula_ruc` varchar(13) NOT NULL,
  `direccion` text NOT NULL,
  `provincia` varchar(100) DEFAULT NULL,
  `canton` varchar(100) DEFAULT NULL,
  `parroquia` varchar(100) DEFAULT NULL,
  `utm_x` varchar(20) DEFAULT NULL,
  `utm_y` varchar(20) DEFAULT NULL,
  `sector` varchar(255) DEFAULT NULL,
  `referencia` varchar(255) DEFAULT NULL,
  `calle_principal` varchar(255) DEFAULT NULL,
  `calle_secundaria` varchar(255) DEFAULT NULL,
  `telefono` varchar(15) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `descripcion` text,
  `archivo_path` varchar(255) DEFAULT NULL,
  `estado` enum('pendiente','revision','aprobado','rechazado') DEFAULT 'pendiente',
  `prioridad` enum('baja','media','alta','urgente') DEFAULT NULL COMMENT 'Solo asignable cuando estado = aprobado. Independiente de construido.',
  `construido` tinyint(1) NOT NULL DEFAULT '0' COMMENT 'Marca fin del ciclo de vida del tramite. 1 = obra/servicio confirmado en inspeccion.',
  `fecha_construido` timestamp NULL DEFAULT NULL COMMENT 'Fecha en que se marco como construido',
  `construido_por` int DEFAULT NULL COMMENT 'Usuario (personal/encargado/admin) que confirmo la construccion',
  `usuario_id` int DEFAULT NULL,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `fecha_actualizacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `encargado_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `numero_tramite` (`numero_tramite`),
  KEY `usuario_id` (`usuario_id`),
  KEY `fk_tramite_encargado` (`encargado_id`),
  KEY `fk_tramite_construido_por` (`construido_por`),
  KEY `idx_estado_construido` (`estado`,`construido`),
  KEY `idx_prioridad` (`prioridad`),
  CONSTRAINT `fk_tramite_construido_por` FOREIGN KEY (`construido_por`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_tramite_encargado` FOREIGN KEY (`encargado_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `tramites_ibfk_1` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla cnel_tramites.tramites_ferum
CREATE TABLE IF NOT EXISTS `tramites_ferum` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tramite_id` int NOT NULL COMMENT 'FK a tramites.id',
  `comunidad` varchar(200) NOT NULL COMMENT 'Nombre del recinto/comunidad/barrio',
  `parroquia` varchar(100) NOT NULL,
  `canton` varchar(100) NOT NULL,
  `provincia` varchar(100) NOT NULL,
  `utm_x` varchar(20) DEFAULT NULL COMMENT 'Coordenada UTM X (opcional al registrar)',
  `utm_y` varchar(20) DEFAULT NULL COMMENT 'Coordenada UTM Y (opcional al registrar)',
  `tipo_sector` enum('rural','urbano_marginal') NOT NULL,
  `num_beneficiarios` int DEFAULT NULL COMMENT 'Número estimado de viviendas/beneficiarios',
  `potencia_requerida` varchar(100) DEFAULT NULL,
  `distancia_red` varchar(100) DEFAULT NULL COMMENT 'Distancia aproximada a la red existente',
  `presidente_nombre` varchar(200) DEFAULT NULL,
  `presidente_cedula` varchar(13) DEFAULT NULL,
  `presidente_celular` varchar(15) DEFAULT NULL,
  `coordinador_nombre` varchar(200) DEFAULT NULL,
  `coordinador_cedula` varchar(13) DEFAULT NULL,
  `coordinador_celular` varchar(15) DEFAULT NULL,
  `horario_contacto` varchar(100) DEFAULT NULL,
  `archivo_croquis` varchar(255) DEFAULT NULL COMMENT 'Croquis de ubicación',
  `archivo_gad` varchar(255) DEFAULT NULL COMMENT 'Certificación de regularización GAD Municipal',
  `archivo_beneficiarios` varchar(255) DEFAULT NULL COMMENT 'Listado de beneficiarios',
  `observaciones` text,
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tramite_id` (`tramite_id`),
  CONSTRAINT `fk_ferum_tramite` FOREIGN KEY (`tramite_id`) REFERENCES `tramites` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla cnel_tramites.tramite_archivos
CREATE TABLE IF NOT EXISTS `tramite_archivos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tramite_id` int NOT NULL,
  `usuario_id` int NOT NULL COMMENT 'Quien subió el archivo',
  `nombre_original` varchar(255) NOT NULL COMMENT 'Nombre original del archivo',
  `archivo_path` varchar(255) NOT NULL COMMENT 'Ruta en disco',
  `descripcion` varchar(300) DEFAULT NULL COMMENT 'Descripción/comentario del archivo',
  `fecha_subida` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tramite_id` (`tramite_id`),
  KEY `idx_usuario_id` (`usuario_id`),
  CONSTRAINT `fk_arch_tramite` FOREIGN KEY (`tramite_id`) REFERENCES `tramites` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_arch_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- La exportación de datos fue deseleccionada.

-- Volcando estructura para tabla cnel_tramites.usuarios
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `rol` enum('admin','encargado','ventanilla','personal') NOT NULL,
  `activo` tinyint(1) DEFAULT '1',
  `fecha_creacion` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- La exportación de datos fue deseleccionada.

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;

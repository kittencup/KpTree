/*
 Navicat Premium Data Transfer

 Source Server         : local
 Source Server Type    : MySQL
 Source Server Version : 50617
 Source Host           : localhost
 Source Database       : kittencupzf2

 Target Server Type    : MySQL
 Target Server Version : 50617
 File Encoding         : utf-8

 Date: 06/03/2014 15:11:35 PM
*/

SET NAMES utf8;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
--  Table structure for `nested`
-- ----------------------------
DROP TABLE IF EXISTS `nested`;
CREATE TABLE `nested` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8_unicode_ci DEFAULT '',
  `l` int(10) unsigned NOT NULL DEFAULT '0',
  `r` int(10) unsigned NOT NULL DEFAULT '0',
  `depth` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `lft` (`l`),
  KEY `rgt` (`r`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- ----------------------------
--  Records of `nested`
-- ----------------------------
BEGIN;
INSERT INTO `nested` VALUES ('1', 'blog', '1', '14', '1'), ('2', 'php', '2', '5', '2'), ('3', 'zf2', '3', '4', '3'), ('4', 'js', '6', '13', '2'), ('5', 'angularjs', '7', '8', '3'), ('6', 'jquery', '9', '12', '3'), ('7', 'jquery-ui', '10', '11', '4');
COMMIT;

SET FOREIGN_KEY_CHECKS = 1;

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

 Date: 06/04/2014 19:58:33 PM
*/

SET NAMES utf8;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
--  Table structure for `pathEnum`
-- ----------------------------
DROP TABLE IF EXISTS `pathEnum`;
CREATE TABLE `pathEnum` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `path` varchar(255) NOT NULL,
  `name` varchar(30) NOT NULL,
  `depth` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Records of `pathEnum`
-- ----------------------------
BEGIN;
INSERT INTO `pathEnum` VALUES ('1', '1/', 'blog', '1'), ('2', '1/2/', 'php', '2'), ('3', '1/3/', 'js', '2'), ('4', '1/2/4/', 'jquery', '3'), ('5', '1/2/5/', 'angularjs', '3'), ('6', '1/2/4/6/', 'jquery-ui', '4'), ('7', '1/2/7/', 'zf2', '3'), ('10', '1/2/7/10/', 'zf3', '4'), ('11', '1/2/7/11/', 'zf4', '4');
COMMIT;

SET FOREIGN_KEY_CHECKS = 1;

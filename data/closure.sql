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

 Date: 06/05/2014 16:33:44 PM
*/

SET NAMES utf8;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
--  Table structure for `closure`
-- ----------------------------
DROP TABLE IF EXISTS `closure`;
CREATE TABLE `closure` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `depth` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Records of `closure`
-- ----------------------------
BEGIN;
INSERT INTO `closure` VALUES ('1', 'blog', '1'), ('2', 'php', '2'), ('3', 'javascript', '2'), ('4', 'zf2', '3'), ('5', 'jquery', '3'), ('6', 'jquery-ui', '4'), ('7', 'angularjs', '3');
COMMIT;

-- ----------------------------
--  Table structure for `closurePaths`
-- ----------------------------
DROP TABLE IF EXISTS `closurePaths`;
CREATE TABLE `closurePaths` (
  `ancestor` int(11) NOT NULL,
  `descendant` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
--  Records of `closurePaths`
-- ----------------------------
BEGIN;
INSERT INTO `closurePaths` VALUES ('1', '1'), ('1', '2'), ('1', '3'), ('1', '4'), ('1', '5'), ('1', '6'), ('1', '7'), ('2', '2'), ('2', '4'), ('3', '3'), ('3', '5'), ('3', '6'), ('3', '7'), ('4', '4'), ('5', '5'), ('5', '6'), ('6', '6'), ('7', '7');
COMMIT;

SET FOREIGN_KEY_CHECKS = 1;

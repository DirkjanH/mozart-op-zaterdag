USE `cfb5wd2sc_mozartopzaterdag`;

ALTER TABLE `activiteit_deelnemers`
  ADD COLUMN `partij` VARCHAR(50) NULL AFTER `instrument_id`;

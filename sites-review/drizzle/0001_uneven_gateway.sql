CREATE TABLE `assistant_usage` (
	`owner` text NOT NULL,
	`hour` integer NOT NULL,
	`count` integer NOT NULL,
	PRIMARY KEY(`owner`, `hour`)
);

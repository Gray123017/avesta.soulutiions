CREATE TABLE `applications` (
	`id` text PRIMARY KEY NOT NULL,
	`owner` text NOT NULL,
	`name` text NOT NULL,
	`phone` text NOT NULL,
	`email` text NOT NULL,
	`data` text NOT NULL,
	`amount` integer NOT NULL,
	`weeks` integer NOT NULL,
	`total` integer NOT NULL,
	`status` text DEFAULT 'Pending' NOT NULL,
	`submitted` text NOT NULL,
	`disbursed` text
);
--> statement-breakpoint
CREATE INDEX `idx_applications_owner_submitted` ON `applications` (`owner`,`submitted`);--> statement-breakpoint
CREATE TABLE `audit` (
	`id` text PRIMARY KEY NOT NULL,
	`owner` text NOT NULL,
	`action` text NOT NULL,
	`reference` text NOT NULL,
	`time` text NOT NULL
);
--> statement-breakpoint
CREATE INDEX `idx_audit_owner_time` ON `audit` (`owner`,`time`);--> statement-breakpoint
CREATE TABLE `documents` (
	`id` text PRIMARY KEY NOT NULL,
	`application_id` text NOT NULL,
	`owner` text NOT NULL,
	`name` text NOT NULL,
	`type` text NOT NULL,
	`size` integer NOT NULL,
	FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON UPDATE no action ON DELETE cascade
);
--> statement-breakpoint
CREATE INDEX `idx_documents_application` ON `documents` (`application_id`);--> statement-breakpoint
CREATE TABLE `payments` (
	`id` text PRIMARY KEY NOT NULL,
	`application_id` text NOT NULL,
	`amount` integer NOT NULL,
	`date` text NOT NULL,
	`method` text NOT NULL,
	`note` text NOT NULL,
	`created` text NOT NULL,
	FOREIGN KEY (`application_id`) REFERENCES `applications`(`id`) ON UPDATE no action ON DELETE cascade
);
--> statement-breakpoint
CREATE INDEX `idx_payments_application` ON `payments` (`application_id`);
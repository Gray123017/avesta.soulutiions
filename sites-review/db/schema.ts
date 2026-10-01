import { sqliteTable, text, integer, index, primaryKey } from 'drizzle-orm/sqlite-core';
export const applications = sqliteTable('applications', {
 id:text('id').primaryKey(), owner:text('owner').notNull(), name:text('name').notNull(),
 phone:text('phone').notNull(), email:text('email').notNull(), data:text('data').notNull(),
 amount:integer('amount').notNull(), weeks:integer('weeks').notNull(), total:integer('total').notNull(),
 status:text('status').notNull().default('Pending'), submitted:text('submitted').notNull(), disbursed:text('disbursed')
}, t=>[index('idx_applications_owner_submitted').on(t.owner,t.submitted)]);
export const documents = sqliteTable('documents', {
 id:text('id').primaryKey(), applicationId:text('application_id').notNull().references(()=>applications.id,{onDelete:'cascade'}),
 owner:text('owner').notNull(), name:text('name').notNull(), type:text('type').notNull(), size:integer('size').notNull()
}, t=>[index('idx_documents_application').on(t.applicationId)]);
export const payments = sqliteTable('payments', {
 id:text('id').primaryKey(), applicationId:text('application_id').notNull().references(()=>applications.id,{onDelete:'cascade'}),
 amount:integer('amount').notNull(), date:text('date').notNull(), method:text('method').notNull(), note:text('note').notNull(), created:text('created').notNull()
}, t=>[index('idx_payments_application').on(t.applicationId)]);
export const audit = sqliteTable('audit', {
 id:text('id').primaryKey(), owner:text('owner').notNull(), action:text('action').notNull(), reference:text('reference').notNull(), time:text('time').notNull()
}, t=>[index('idx_audit_owner_time').on(t.owner,t.time)]);

export const assistantUsage=sqliteTable("assistant_usage", {owner:text("owner").notNull(),hour:integer("hour").notNull(),count:integer("count").notNull()}, t=>[primaryKey({columns:[t.owner,t.hour]})]);

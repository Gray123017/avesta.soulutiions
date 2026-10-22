<?php
declare(strict_types=1);
if (!defined('AV7_BOOTSTRAP')) { http_response_code(404); exit; }
// Fictional read-only data. No loan amounts, repayment ledger or documents in this gate.
return [
 ['id'=>'DEMO-APP-101','kind'=>'application','title'=>'Fictional application A1','status'=>'Pending','owner_id'=>'DEMO-U-B1','assigned_to'=>'DEMO-U-S1','owner_label'=>'Demo Borrower A','assignee_label'=>'Demo Staff A','sample_note'=>'Read-only example. This is not a submitted or approved loan.','internal_note'=>'Must not be serialized'],
 ['id'=>'DEMO-APP-102','kind'=>'application','title'=>'Fictional application B1','status'=>'Under Review','owner_id'=>'DEMO-U-B2','assigned_to'=>'DEMO-U-S2','owner_label'=>'Demo Borrower B','assignee_label'=>'Demo Staff B','sample_note'=>'Read-only example. No financial obligation is created.','internal_note'=>'Must not be serialized'],
 ['id'=>'DEMO-APP-103','kind'=>'application','title'=>'Fictional application A2','status'=>'Pending','owner_id'=>'DEMO-U-B1','assigned_to'=>'','owner_label'=>'Demo Borrower A','assignee_label'=>'Unassigned','sample_note'=>'An unassigned fixture, not a real application.','internal_note'=>'Must not be serialized'],
 ['id'=>'DEMO-TASK-201','kind'=>'support','title'=>'Sample Wi-Fi enquiry','status'=>'Open','owner_id'=>'DEMO-U-B1','assigned_to'=>'DEMO-U-S1','owner_label'=>'Demo Borrower A','assignee_label'=>'Demo Staff A','sample_note'=>'No technician is notified or scheduled.','internal_note'=>'Must not be serialized'],
 ['id'=>'DEMO-TASK-202','kind'=>'support','title'=>'Sample software enquiry','status'=>'In progress','owner_id'=>'DEMO-U-B2','assigned_to'=>'DEMO-U-S2','owner_label'=>'Demo Borrower B','assignee_label'=>'Demo Staff B','sample_note'=>'No installer is downloaded or email sent.','internal_note'=>'Must not be serialized']
];

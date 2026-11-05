<?php
/**
 * The employer register.
 *
 * The check-off channel rests on this. An employer signs up with Avesta, lists
 * its branches, enrols its staff, and from then on deducts their repayments at
 * payroll and remits the money here. The employee is the borrower; the employer
 * collects. Nothing in this file lends money or moves any — it is the register
 * of who works where, which is what every later step has to be able to trust.
 *
 * Two rules are worth more than the rest, because breaking either one takes
 * money off the wrong person's wages:
 *
 *   One payroll number, one person, within an employer. Two people sharing one
 *   means the payroll office deducts from whoever the number resolves to.
 *
 *   One active enrolment per person, across all employers. Two means two
 *   payrolls both deducting for the same loan.
 *
 * Someone who leaves is marked as having left and KEPT. There is usually a
 * balance to settle out of a final pay, and a deleted record cannot be settled
 * against. Nothing here ever removes a person.
 *
 * Stored through the same guarded store as every other data file, so fetching
 * it over the web answers 404 rather than handing over the staff register.
 */

require_once __DIR__ . '/auth.php';

const AV_EMPLOYER_STATUSES = ['pending', 'active', 'suspended'];

/**
 * Where the register lives. Call with a path once, at the top of a test, to
 * point the whole library at a throwaway file.
 */
function av_employers_path(?string $set = null): string {
    static $path = null;
    if ($set !== null) { $path = $set; av_employers_reset_cache(); }
    return $path ?? (__DIR__ . '/employers.json');
}

/** The file actually on disk — the guarded .php, not the legacy .json name. */
function av_employers_store(): string {
    return avStorePath(av_employers_path());
}

function av_employers_reset_cache(): void {
    av_employers_cache(null, true);
}

/** Read-through cache, so a page that asks several questions reads one file. */
function av_employers_cache(?array $set = null, bool $clear = false): ?array {
    static $data = null;
    if ($clear) { $data = null; return null; }
    if ($set !== null) $data = $set;
    return $data;
}

function av_employers_load(): array {
    $cached = av_employers_cache();
    if ($cached !== null) return $cached;

    $data = avStoreRead(av_employers_path());
    if (!is_array($data) || !isset($data['employers']) || !is_array($data['employers'])) {
        $data = ['version' => 1, 'employers' => []];
    }
    av_employers_cache($data);
    return $data;
}

function av_employers_save(array $data): bool {
    $data['version'] = 1;
    av_employers_cache($data);
    return avStoreWrite(av_employers_path(), $data);
}

/** Every employer, register and all. Staff included — admin use only. */
function av_employers_all(): array {
    return av_employers_load()['employers'];
}

function av_employer_find(string $id): ?array {
    foreach (av_employers_all() as $e) {
        if (($e['id'] ?? '') === $id) return $e;
    }
    return null;
}

/** Replace one employer in place. Returns false if it is not there any more. */
function av_employer_put(array $employer): bool {
    $data = av_employers_load();
    foreach ($data['employers'] as $i => $e) {
        if (($e['id'] ?? '') === $employer['id']) {
            $data['employers'][$i] = $employer;
            return av_employers_save($data);
        }
    }
    return false;
}

/** Names are compared with spacing and case ignored, so one company is one record. */
function av_employer_key(string $name): string {
    return strtolower(preg_replace('/\s+/', ' ', trim($name)));
}

function av_employer_id(string $prefix): string {
    return $prefix . '_' . bin2hex(random_bytes(6));
}

// ── employers ────────────────────────────────────────────────────────────────

/**
 * Register an employer. It starts pending: nobody can borrow against an
 * employer until a person has checked that the employer agreed to any of this.
 */
function av_employer_create(array $in): array {
    $name = trim((string) ($in['name'] ?? ''));
    if ($name === '') return ['ok' => false, 'error' => 'An employer needs a name.'];

    foreach (av_employers_all() as $e) {
        if (av_employer_key($e['name'] ?? '') === av_employer_key($name)) {
            return ['ok' => false,
                    'error' => 'That employer is already registered. Two records for one '
                             . 'company would split its staff across both.',
                    'employer' => $e];
        }
    }

    $employer = [
        'id'              => av_employer_id('emp'),
        'name'            => $name,
        'registration_no' => trim((string) ($in['registration_no'] ?? '')),
        'contact_name'    => trim((string) ($in['contact_name'] ?? '')),
        'contact_phone'   => trim((string) ($in['contact_phone'] ?? '')),
        'contact_email'   => trim((string) ($in['contact_email'] ?? '')),
        'notes'           => trim((string) ($in['notes'] ?? '')),
        'status'          => 'pending',
        'created_at'      => date('c'),
        'created_by'      => av_user()['username'] ?? 'system',
        'branches'        => [],
        'employees'       => [],
    ];

    $data = av_employers_load();
    $data['employers'][] = $employer;
    if (!av_employers_save($data)) {
        return ['ok' => false, 'error' => 'The register could not be written.'];
    }
    return ['ok' => true, 'employer' => $employer];
}

function av_employer_set_status(string $id, string $status): array {
    if (!in_array($status, AV_EMPLOYER_STATUSES, true)) {
        return ['ok' => false, 'error' => 'Unknown status.'];
    }
    $employer = av_employer_find($id);
    if ($employer === null) return ['ok' => false, 'error' => 'Employer not found.'];

    $employer['status'] = $status;
    $employer['status_changed_at'] = date('c');
    if (!av_employer_put($employer)) return ['ok' => false, 'error' => 'Could not save.'];
    return ['ok' => true, 'employer' => $employer];
}

function av_employer_update(string $id, array $in): array {
    $employer = av_employer_find($id);
    if ($employer === null) return ['ok' => false, 'error' => 'Employer not found.'];

    foreach (['registration_no', 'contact_name', 'contact_phone', 'contact_email', 'notes'] as $f) {
        if (array_key_exists($f, $in)) $employer[$f] = trim((string) $in[$f]);
    }
    if (array_key_exists('name', $in)) {
        $name = trim((string) $in['name']);
        if ($name === '') return ['ok' => false, 'error' => 'An employer needs a name.'];
        foreach (av_employers_all() as $other) {
            if (($other['id'] ?? '') !== $id
                && av_employer_key($other['name'] ?? '') === av_employer_key($name)) {
                return ['ok' => false, 'error' => 'Another employer is already registered under that name.'];
            }
        }
        $employer['name'] = $name;
    }
    if (!av_employer_put($employer)) return ['ok' => false, 'error' => 'Could not save.'];
    return ['ok' => true, 'employer' => $employer];
}

// ── branches ─────────────────────────────────────────────────────────────────

function av_employer_add_branch(string $employerId, string $name, string $town = ''): array {
    $employer = av_employer_find($employerId);
    if ($employer === null) return ['ok' => false, 'error' => 'Employer not found.'];

    $name = trim($name);
    if ($name === '') return ['ok' => false, 'error' => 'A branch needs a name.'];

    foreach ($employer['branches'] as $b) {
        if (av_employer_key($b['name'] ?? '') === av_employer_key($name)) {
            return ['ok' => false, 'error' => 'That branch is already listed.', 'branch' => $b];
        }
    }

    $branch = ['id' => av_employer_id('br'), 'name' => $name,
               'town' => trim($town), 'created_at' => date('c')];
    $employer['branches'][] = $branch;
    if (!av_employer_put($employer)) return ['ok' => false, 'error' => 'Could not save.'];
    return ['ok' => true, 'branch' => $branch, 'employer' => $employer];
}

/**
 * Remove a branch nobody is assigned to.
 *
 * Refused while staff are on it: an enrolment pointing at a branch that no
 * longer exists is a deduction with nowhere to be collected, and the people it
 * would strand are exactly the ones already repaying.
 */
function av_employer_remove_branch(string $employerId, string $branchId): array {
    $employer = av_employer_find($employerId);
    if ($employer === null) return ['ok' => false, 'error' => 'Employer not found.'];

    $onIt = 0;
    foreach ($employer['employees'] as $e) {
        if (($e['branch_id'] ?? '') === $branchId && ($e['status'] ?? '') === 'active') $onIt++;
    }
    if ($onIt > 0) {
        return ['ok' => false,
                'error' => $onIt . ' member(s) of staff are still assigned to that branch. '
                         . 'Move them first.'];
    }

    $kept = array_values(array_filter($employer['branches'],
        fn($b) => ($b['id'] ?? '') !== $branchId));
    if (count($kept) === count($employer['branches'])) {
        return ['ok' => false, 'error' => 'Branch not found.'];
    }
    $employer['branches'] = $kept;
    if (!av_employer_put($employer)) return ['ok' => false, 'error' => 'Could not save.'];
    return ['ok' => true, 'employer' => $employer];
}

function av_employer_branch(array $employer, string $branchId): ?array {
    foreach ($employer['branches'] as $b) {
        if (($b['id'] ?? '') === $branchId) return $b;
    }
    return null;
}

// ── staff ────────────────────────────────────────────────────────────────────

/** The payroll number as it is compared: spacing and case do not distinguish. */
function av_payroll_key(string $no): string {
    return strtolower(preg_replace('/\s+/', '', trim($no)));
}

/** The enrolment a person is currently on, anywhere, or null. */
function av_employer_active_enrolment(string $userId): ?array {
    foreach (av_employers_all() as $employer) {
        foreach ($employer['employees'] as $e) {
            if (($e['user_id'] ?? '') === $userId && ($e['status'] ?? '') === 'active') {
                return ['employer' => $employer, 'enrolment' => $e];
            }
        }
    }
    return null;
}

/**
 * Where this person works, for the application form and their own account
 * page: the employer, the branch and their enrolment, or null.
 */
function av_employer_for_user(string $userId): ?array {
    $found = av_employer_active_enrolment($userId);
    if ($found === null) return null;

    $employer = $found['employer'];
    return [
        'employer'  => ['id' => $employer['id'], 'name' => $employer['name'],
                        'status' => $employer['status']],
        'branch'    => av_employer_branch($employer, $found['enrolment']['branch_id'] ?? ''),
        'enrolment' => $found['enrolment'],
    ];
}

/**
 * Put somebody on an employer's payroll register.
 *
 * Refused if the payroll number is taken at this employer, or if the person is
 * already active somewhere. Both of those end with a deduction coming off the
 * wrong salary, which is worse than an enrolment that has to be done twice.
 */
function av_employer_enrol(string $employerId, array $in): array {
    $employer = av_employer_find($employerId);
    if ($employer === null) return ['ok' => false, 'error' => 'Employer not found.'];

    $userId    = trim((string) ($in['user_id'] ?? ''));
    $name      = trim((string) ($in['name'] ?? ''));
    $payrollNo = trim((string) ($in['payroll_no'] ?? ''));
    $branchId  = trim((string) ($in['branch_id'] ?? ''));

    if ($name === '')      return ['ok' => false, 'error' => 'The employee needs a name.'];
    if ($payrollNo === '') return ['ok' => false, 'error' => 'A payroll number is required — it is what the deduction is matched against.'];

    if (av_employer_branch($employer, $branchId) === null) {
        return ['ok' => false, 'error' => 'That branch does not belong to this employer.'];
    }

    foreach ($employer['employees'] as $e) {
        if (($e['status'] ?? '') !== 'active') continue;
        if (av_payroll_key($e['payroll_no'] ?? '') === av_payroll_key($payrollNo)) {
            return ['ok' => false,
                    'error' => 'That payroll number is already in use at this employer. '
                             . 'A repayment would be deducted from the wrong salary.'];
        }
    }

    if ($userId !== '') {
        $already = av_employer_active_enrolment($userId);
        if ($already !== null) {
            return ['ok' => false,
                    'error' => 'That person is already enrolled with '
                             . $already['employer']['name'] . '. Mark them as having left '
                             . 'there first, or both payrolls will deduct.'];
        }
    }

    $enrolment = [
        'id'          => av_employer_id('enr'),
        'user_id'     => $userId,
        'name'        => $name,
        'payroll_no'  => $payrollNo,
        'branch_id'   => $branchId,
        'status'      => 'active',
        'enrolled_at' => date('c'),
        'enrolled_by' => av_user()['username'] ?? 'system',
        'left_at'     => null,
    ];
    $employer['employees'][] = $enrolment;
    if (!av_employer_put($employer)) return ['ok' => false, 'error' => 'Could not save.'];
    return ['ok' => true, 'enrolment' => $enrolment, 'employer' => $employer];
}

/**
 * Mark somebody as having left. Never deletes them.
 *
 * There is usually a balance to settle out of a final pay, and the audit trail
 * of who was enrolled when is the thing a dispute is settled from.
 */
function av_employer_mark_left(string $employerId, string $enrolmentId, string $date = ''): array {
    $employer = av_employer_find($employerId);
    if ($employer === null) return ['ok' => false, 'error' => 'Employer not found.'];

    foreach ($employer['employees'] as $i => $e) {
        if (($e['id'] ?? '') !== $enrolmentId) continue;

        $employer['employees'][$i]['status']  = 'left';
        $employer['employees'][$i]['left_at'] = $date !== '' ? $date : date('Y-m-d');
        $employer['employees'][$i]['left_by'] = av_user()['username'] ?? 'system';
        if (!av_employer_put($employer)) return ['ok' => false, 'error' => 'Could not save.'];
        return ['ok' => true, 'enrolment' => $employer['employees'][$i]];
    }
    return ['ok' => false, 'error' => 'That enrolment is not on this employer.'];
}

// ── what the application form is allowed to see ──────────────────────────────

/**
 * The employers an applicant may pick, and their branches. Nothing else.
 *
 * This list goes to every signed-in applicant, so it carries no staff, no
 * payroll numbers and no contact details — only what is needed to choose. An
 * employer that is pending or suspended is not on it: a check-off loan against
 * an employer that has not signed, or has stopped remitting, has no way to be
 * collected.
 */
function av_employers_choosable(): array {
    $out = [];
    foreach (av_employers_all() as $e) {
        if (($e['status'] ?? '') !== 'active') continue;

        $branches = [];
        foreach ($e['branches'] as $b) {
            $branches[] = ['id' => $b['id'], 'name' => $b['name'], 'town' => $b['town'] ?? ''];
        }
        $out[] = ['id' => $e['id'], 'name' => $e['name'], 'branches' => $branches];
    }
    usort($out, fn($a, $b) => strcasecmp($a['name'], $b['name']));
    return $out;
}

/** Is this employer-and-branch pair one an application may actually name? */
function av_employer_choice_valid(string $employerId, string $branchId): bool {
    foreach (av_employers_choosable() as $e) {
        if ($e['id'] !== $employerId) continue;
        if ($branchId === '') return true;
        foreach ($e['branches'] as $b) {
            if ($b['id'] === $branchId) return true;
        }
        return false;
    }
    return false;
}

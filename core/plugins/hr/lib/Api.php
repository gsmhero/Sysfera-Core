<?php
declare(strict_types=1);
namespace SysferaHr;

/**
 * ONE registry of operations. The signed machine API (/api/hr/v1/...), the session API used by the pages' scripts (/p/hr/api/...) and the HTML forms of the UI all call
 * the same handlers, so there is no feature the API lacks. Every operation takes (Ctx, input) and the engine checks the permission itself.
 * ROUTES is the REST surface; openapi.yaml documents it and tests/hr.php + tests/hrviews.php check that the two agree.
 */
final class Api
{
    /** [method, path (relative to the API base), operation] */
    public const ROUTES = [
        ['GET', 'time', 'time'], ['GET', 'me', 'me'], ['GET', 'rbac/catalog', 'rbac.catalog'],
        ['GET', 'roles', 'roles.list'], ['POST', 'roles', 'roles.save'], ['POST', 'roles/{uuid}/active', 'roles.active'],
        ['GET', 'vacancies', 'vacancies.list'], ['POST', 'vacancies', 'vacancies.save'], ['GET', 'vacancies/{uuid}', 'vacancies.get'], ['GET', 'vacancies/{uuid}/versions', 'vacancies.versions'],
        ['POST', 'vacancies/{uuid}/approve', 'vacancies.approve'], ['POST', 'vacancies/{uuid}/publish', 'vacancies.publish'], ['POST', 'vacancies/{uuid}/close', 'vacancies.close'], ['POST', 'vacancies/{uuid}/archive', 'vacancies.archive'],
        ['GET', 'vacancies/{uuid}/board', 'candidates.board'], ['POST', 'vacancies/{uuid}/candidates', 'candidates.add'],
        ['GET', 'candidates/{uuid}', 'candidates.history'], ['POST', 'candidates/{uuid}/move', 'candidates.move'], ['POST', 'candidates/{uuid}/hire', 'candidates.hire'], ['POST', 'candidates/{uuid}/interviews', 'interviews.schedule'],
        ['POST', 'interviews/{uuid}/cancel', 'interviews.cancel'], ['POST', 'interviews/{uuid}/feedback', 'interviews.feedback'], ['GET', 'interviewers/{user_id}/slots', 'interviews.slots'],
        ['GET', 'employees', 'employees.list'], ['POST', 'employees', 'employees.create'], ['GET', 'employees/{uuid}', 'employees.get'], ['POST', 'employees/{uuid}', 'employees.update'], ['GET', 'employees/{uuid}/timeline', 'employees.timeline'],
        ['GET', 'employees/{uuid}/documents', 'docs.list'], ['POST', 'employees/{uuid}/documents', 'docs.add'], ['POST', 'documents/{uuid}/sign', 'docs.sign'], ['POST', 'documents/{uuid}/expire', 'docs.expire'],
        ['GET', 'employees/{uuid}/pay', 'pay.get'], ['POST', 'employees/{uuid}/pay', 'pay.set'],
        ['GET', 'employees/{uuid}/tasks', 'tasks.list'], ['POST', 'employees/{uuid}/tasks', 'tasks.add'], ['POST', 'tasks/{uuid}/done', 'tasks.done'], ['GET', 'onboarding/alerts', 'onboarding.alerts'],
        ['POST', 'employees/{uuid}/offboard', 'offboard'], ['POST', 'employees/{uuid}/consents', 'consent.set'], ['GET', 'consents/missing', 'consent.missing'], ['POST', 'exports/employees', 'export.employees'],
        ['GET', 'employees/{uuid}/leave-balance', 'leave.balance'],
        ['GET', 'teams', 'teams.list'], ['POST', 'teams', 'teams.save'], ['POST', 'teams/{uuid}/members', 'teams.member'],
        ['GET', 'schedules', 'schedules.list'], ['POST', 'schedules', 'schedules.save'], ['GET', 'warehouses/{id}/shift', 'schedules.shift'],
        ['GET', 'leave', 'leave.list'], ['POST', 'leave', 'leave.request'], ['GET', 'leave/calendar', 'leave.calendar'], ['POST', 'leave/{uuid}/decision', 'leave.decide'], ['POST', 'leave/{uuid}/cancel', 'leave.cancel'],
        ['GET', 'users/{user_id}/access', 'rbac.user'], ['POST', 'users/{user_id}/roles', 'rbac.grant'], ['POST', 'users/{user_id}/roles/{role_uuid}/revoke', 'rbac.revoke'], ['GET', 'users/{user_id}/can', 'rbac.check'],
        ['GET', 'access/orphans', 'rbac.orphans'], ['POST', 'access/orphans/revoke', 'rbac.revoke_orphans'],
        ['GET', 'payroll', 'payroll.calc'], ['POST', 'payroll/export', 'payroll.export'],
        ['GET', 'audit', 'audit.list'], ['GET', 'audit/verify', 'audit.verify'],
    ];

    /** @return array<string, callable(Ctx, array): mixed> */
    public static function ops(Env $e, Hr $hr, Recruit $rec, Leave $lv): array
    {
        $u = fn(array $in): string => (string)($in['uuid'] ?? '');
        $uid = fn(array $in): int => (int)($in['user_id'] ?? 0);
        $bool = fn($v): bool => $v === true || $v === 1 || $v === '1' || $v === 'true' || $v === 'on';
        return [
            'time' => fn(Ctx $c, array $in) => ['time' => time(), 'iso' => date('c')],
            'me' => function (Ctx $c, array $in) use ($e, $hr) {
                $emp = $c->uid ? $e->empOfUser($c->uid) : null;
                return ['user_id' => $c->uid, 'name' => $c->name, 'root' => $c->root, 'permissions' => $c->perms, 'roles' => $c->uid ? array_map(fn($r) => $r['code'], $e->rbac->rolesOf($c->uid)) : [], 'employee' => $emp ? $hr->view($c, $emp) : null];
            },
            'rbac.catalog' => fn(Ctx $c, array $in) => ['permissions' => Rbac::CATALOG, 'goods_issue' => Rbac::GI_PERMS, 'builtin_roles' => array_map(fn($b) => ['name' => $b[0], 'name_ru' => $b[1], 'permissions' => $b[2]], Rbac::BUILTIN)],
            'roles.list' => fn(Ctx $c, array $in) => $hr->listRoles($c),
            'roles.save' => fn(Ctx $c, array $in) => $hr->saveRole($c, $in),
            'roles.active' => fn(Ctx $c, array $in) => $hr->setRoleActive($c, $u($in), $bool($in['active'] ?? false)),
            'vacancies.list' => fn(Ctx $c, array $in) => $rec->listVacancies($c, $in),
            'vacancies.save' => fn(Ctx $c, array $in) => $rec->saveVacancy($c, $in),
            'vacancies.get' => fn(Ctx $c, array $in) => $rec->getVacancy($c, $u($in)),
            'vacancies.versions' => fn(Ctx $c, array $in) => $rec->versions($c, $u($in)),
            'vacancies.approve' => fn(Ctx $c, array $in) => $rec->approve($c, $u($in)),
            'vacancies.publish' => fn(Ctx $c, array $in) => $rec->publish($c, $u($in), (string)($in['audience'] ?? 'global')),
            'vacancies.close' => fn(Ctx $c, array $in) => $rec->closeVacancy($c, $u($in)),
            'vacancies.archive' => fn(Ctx $c, array $in) => $rec->archive($c, $u($in)),
            'candidates.board' => fn(Ctx $c, array $in) => $rec->board($c, $u($in)),
            'candidates.add' => fn(Ctx $c, array $in) => $rec->addCandidate($c, $u($in), array_diff_key($in, ['uuid' => 1]) + (isset($in['candidate_uuid']) ? ['uuid' => $in['candidate_uuid']] : [])),
            'candidates.history' => fn(Ctx $c, array $in) => $rec->candidateHistory($c, $u($in)),
            'candidates.move' => fn(Ctx $c, array $in) => $rec->move($c, $u($in), (string)($in['stage'] ?? ''), (string)($in['note'] ?? '')),
            'candidates.hire' => fn(Ctx $c, array $in) => $rec->hire($c, $u($in), $in),
            'interviews.schedule' => fn(Ctx $c, array $in) => $rec->schedule($c, $u($in), array_diff_key($in, ['uuid' => 1]) + (isset($in['interview_uuid']) ? ['uuid' => $in['interview_uuid']] : [])),
            'interviews.cancel' => fn(Ctx $c, array $in) => $rec->cancelInterview($c, $u($in)),
            'interviews.feedback' => fn(Ctx $c, array $in) => $rec->feedback($c, $u($in), $in),
            'interviews.slots' => fn(Ctx $c, array $in) => $rec->slots($c, $uid($in), (string)($in['day'] ?? '')),
            'employees.list' => fn(Ctx $c, array $in) => $hr->listEmployees($c, $in),
            'employees.create' => fn(Ctx $c, array $in) => $hr->createEmployee($c, $in),
            'employees.get' => fn(Ctx $c, array $in) => $hr->getEmployee($c, $u($in)),
            'employees.update' => fn(Ctx $c, array $in) => $hr->updateEmployee($c, $u($in), array_diff_key($in, ['uuid' => 1, '_t' => 1, 'op' => 1])),
            'employees.timeline' => fn(Ctx $c, array $in) => $hr->timeline($c, $u($in)),
            'docs.list' => fn(Ctx $c, array $in) => $hr->listDocs($c, $u($in)),
            'docs.add' => fn(Ctx $c, array $in) => $hr->addDoc($c, $u($in), array_diff_key($in, ['uuid' => 1]) + (isset($in['doc_uuid']) ? ['uuid' => $in['doc_uuid']] : [])),
            'docs.sign' => fn(Ctx $c, array $in) => $hr->signDoc($c, $u($in), $in),
            'docs.expire' => fn(Ctx $c, array $in) => $hr->expireDoc($c, $u($in)),
            'pay.get' => fn(Ctx $c, array $in) => $hr->getPay($c, $u($in)),
            'pay.set' => fn(Ctx $c, array $in) => $hr->setPay($c, $u($in), $in),
            'tasks.list' => fn(Ctx $c, array $in) => $hr->listTasks($c, $u($in), (string)($in['phase'] ?? 'onboarding')),
            'tasks.add' => fn(Ctx $c, array $in) => $hr->addTask($c, $u($in), (string)($in['phase'] ?? 'onboarding'), (string)($in['code'] ?? ''), (string)($in['note'] ?? '')),
            'tasks.done' => fn(Ctx $c, array $in) => $hr->doneTask($c, $u($in), !array_key_exists('done', $in) || $bool($in['done'])),
            'onboarding.alerts' => fn(Ctx $c, array $in) => $hr->onboardingAlerts($c, !isset($in['notify']) || $bool($in['notify'])),
            'offboard' => fn(Ctx $c, array $in) => $hr->offboard($c, $u($in), $in),
            'consent.set' => fn(Ctx $c, array $in) => $hr->consent($c, $u($in), (string)($in['kind'] ?? 'personal_data'), (string)($in['action'] ?? 'grant')),
            'consent.missing' => fn(Ctx $c, array $in) => $hr->missingConsent($c),
            'export.employees' => fn(Ctx $c, array $in) => $hr->exportEmployees($c, $in),
            'leave.balance' => fn(Ctx $c, array $in) => $lv->balance($c, $u($in)),
            'teams.list' => fn(Ctx $c, array $in) => $hr->listTeams($c),
            'teams.save' => fn(Ctx $c, array $in) => $hr->saveTeam($c, $in),
            'teams.member' => fn(Ctx $c, array $in) => $hr->setMember($c, $u($in), (string)($in['employee_uuid'] ?? ''), (string)($in['role'] ?? 'member'), $bool($in['remove'] ?? false)),
            'schedules.list' => fn(Ctx $c, array $in) => $lv->listSchedules($c),
            'schedules.save' => fn(Ctx $c, array $in) => $lv->saveSchedule($c, $in),
            'schedules.shift' => fn(Ctx $c, array $in) => $lv->shift($c, (int)($in['id'] ?? 0), (string)($in['day'] ?? $e->today())),
            'leave.list' => fn(Ctx $c, array $in) => $lv->listLeave($c, $in),
            'leave.request' => fn(Ctx $c, array $in) => $lv->request($c, $in),
            'leave.calendar' => fn(Ctx $c, array $in) => $lv->calendar($c, (string)($in['month'] ?? substr($e->today(), 0, 7)), ($in['team_uuid'] ?? '') !== '' ? (string)$in['team_uuid'] : null),
            'leave.decide' => fn(Ctx $c, array $in) => $lv->decide($c, $u($in), (string)($in['decision'] ?? ''), (string)($in['note'] ?? '')),
            'leave.cancel' => fn(Ctx $c, array $in) => $lv->cancel($c, $u($in)),
            'rbac.user' => fn(Ctx $c, array $in) => $hr->userAccess($c, $uid($in)),
            'rbac.grant' => fn(Ctx $c, array $in) => $hr->grant($c, $uid($in), (string)($in['role_uuid'] ?? '')),
            'rbac.revoke' => fn(Ctx $c, array $in) => $hr->revoke($c, $uid($in), (string)($in['role_uuid'] ?? '')),
            'rbac.check' => fn(Ctx $c, array $in) => $hr->check($c, $uid($in), (string)($in['permission'] ?? '')),
            'rbac.orphans' => fn(Ctx $c, array $in) => $hr->orphans($c),
            'rbac.revoke_orphans' => fn(Ctx $c, array $in) => $hr->revokeOrphans($c),
            'payroll.calc' => fn(Ctx $c, array $in) => $lv->payroll($c, (string)($in['from'] ?? ''), (string)($in['to'] ?? ''), (array)($in['commission_base'] ?? []), false),
            'payroll.export' => fn(Ctx $c, array $in) => $lv->payroll($c, (string)($in['from'] ?? ''), (string)($in['to'] ?? ''), (array)($in['commission_base'] ?? []), true),
            'audit.list' => fn(Ctx $c, array $in) => $hr->auditRows($c, $in),
            'audit.verify' => fn(Ctx $c, array $in) => $hr->verifyAudit($c),
        ];
    }

    /** turn "employees/{uuid}/pay" into a regex-free matcher: returns the path params or null */
    public static function match(string $pattern, string $path): ?array
    {
        $re = '#^' . preg_replace('/\\\{([a-z_]+)\\\}/', '(?P<$1>[^/]+)', preg_quote($pattern, '#')) . '$#';
        return preg_match($re, $path, $m) ? array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY) : null;
    }
}

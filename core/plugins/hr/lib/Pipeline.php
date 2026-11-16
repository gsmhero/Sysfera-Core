<?php
declare(strict_types=1);
namespace SysferaHr;

/** State machines of recruiting: vacancy workflow and candidate stages. Pure. */
final class Pipeline
{
    public const STAGES = ['new', 'screening', 'interview', 'offer', 'hired', 'rejected'];
    public const NEXT = ['new' => ['screening', 'rejected'], 'screening' => ['interview', 'rejected'], 'interview' => ['offer', 'rejected'], 'offer' => ['hired', 'rejected'], 'hired' => [], 'rejected' => []];

    /** vacancy workflow: draft -> approved -> published -> closed (a published or approved vacancy whose text changed goes back to draft) */
    public const WF = ['draft' => ['approved', 'closed'], 'approved' => ['published', 'draft', 'closed'], 'published' => ['closed'], 'closed' => []];
    public const STATUS = ['open', 'closed', 'archived'];

    public static function canMove(string $from, string $to): bool { return in_array($to, self::NEXT[$from] ?? [], true); }
    public static function canWf(string $from, string $to): bool { return in_array($to, self::WF[$from] ?? [], true); }

    public const CRITERIA = ['skills', 'communication', 'culture', 'motivation'];
    public const RECOMMEND = ['strong_yes', 'yes', 'no', 'strong_no'];

    /** structured interviewer feedback: scores 1..5 per criterion, a recommendation, a comment. @return array normalized or throws */
    public static function feedback(array $in): array
    {
        $scores = [];
        foreach (self::CRITERIA as $k) { $v = $in['scores'][$k] ?? null; if (!is_numeric($v) || (int)$v < 1 || (int)$v > 5) throw new Err('bad_feedback', "scores.$k must be 1..5", 422); $scores[$k] = (int)$v; }
        $rec = (string)($in['recommend'] ?? ''); if (!in_array($rec, self::RECOMMEND, true)) throw new Err('bad_feedback', 'recommend: ' . implode('|', self::RECOMMEND), 422);
        return ['scores' => $scores, 'recommend' => $rec, 'comment' => substr(trim((string)($in['comment'] ?? '')), 0, 2000), 'avg' => round(array_sum($scores) / count($scores), 2)];
    }
}

<?php

declare(strict_types=1);

namespace MigrationModule\Application\Cutover;

final class GoLiveDecisionEngine
{
    /** @param array<string,mixed> $context @return array<string,mixed> */
    public function decide(array $context): array
    {
        $reasons = [];
        $blockers = [];
        $warnings = [];

        $readiness = (string) ($context['readiness_status'] ?? 'blocked');
        $verificationColor = (string) ($context['verification_color'] ?? 'red');
        $blockingMutations = (int) ($context['blocking_mutations'] ?? 0);
        $criticalErrors = (int) ($context['unresolved_critical_errors'] ?? 0);
        $deltaFailures = (int) ($context['delta_failed_count'] ?? 0);
        $allowYellow = (bool) ($context['allow_yellow_with_override'] ?? true);

        if (($context['evidence']['readiness_status']['provenance'] ?? '') === 'unavailable') {
            $blockers[] = 'readiness_evidence_unavailable';
        }
        if (($context['evidence']['verification_color']['provenance'] ?? '') === 'unavailable') {
            $blockers[] = 'verification_evidence_unavailable';
        }

        if ($readiness === 'blocked') {
            $blockers[] = 'readiness_blocked';
        } elseif ($readiness === 'pass_with_warnings') {
            $warnings[] = 'readiness_warnings_present';
        }

        if ($blockingMutations > 0) {
            $blockers[] = 'blocking_mutations_detected';
        }
        if ($criticalErrors > 0) {
            $blockers[] = 'unresolved_critical_errors';
        }
        if ($deltaFailures > 0) {
            $blockers[] = 'final_delta_failures';
        }

        if ($verificationColor === 'red') {
            $blockers[] = 'verification_red';
        }
        if ($verificationColor === 'yellow') {
            $warnings[] = 'verification_yellow';
        }

        $verdict = 'go_live_blocked';
        $overrideAllowed = false;
        $overrideRisk = 'high';
        if ($blockers === [] && $verificationColor === 'green' && $readiness !== 'blocked') {
            $verdict = 'go_live_approved';
            $overrideRisk = 'low';
            $reasons[] = 'all_gates_green';
        } elseif ($blockers === [] && $verificationColor === 'yellow' && $allowYellow) {
            $verdict = 'operator_review_required';
            $overrideAllowed = true;
            $overrideRisk = 'medium';
            $reasons[] = 'yellow_needs_explicit_override';
        }

        return [
            'verdict' => $verdict,
            'reasons' => $reasons,
            'blockers' => $blockers,
            'warnings' => array_values(array_unique($warnings)),
            'recommended_next_actions' => $verdict === 'go_live_approved' ? ['proceed_cutover_complete'] : ['review_blockers', 'rerun_verification'],
            'override_allowed' => $overrideAllowed,
            'override_risk_level' => $overrideRisk,
        ];
    }
}

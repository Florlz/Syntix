export const blockerLabels = {
    judging_panel_unlocked: 'The judging panel has not been locked.',
    panel_empty: 'A Judge panel still needs to be configured.',
    rule_version_missing: 'The scoring rule is missing from this contest.',
    source_blocked: 'The approved source has an unresolved conflict.',
    rule_not_frozen: 'The scoring rule must be frozen before tabulation.',
    aggregation_confirmation_missing: 'The Judge aggregation method still needs Admin confirmation.',
    missing_scorecards: 'Waiting for all Judges to submit their scorecards.',
    adjustment_calculation_unauthorized: 'The deduction calculation policy requires Admin authorization.',
    adjustment_evidence_missing: 'Required deduction evidence is still missing.',
    aggregation_method_unsupported: 'This aggregation method is not supported for finalization.',
    tie_resolution_required: 'The tied result requires an authorized Admin resolution.',
    scorecard_rule_mismatch: 'One or more Judge scorecards use the wrong scoring rule.',
};

export function presentBlockers(readiness = {}) {
    return (readiness.blocker_codes ?? []).map((code) => readiness.blocker_labels?.[code] ?? blockerLabels[code] ?? code.replaceAll('_', ' '));
}

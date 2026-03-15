<?php

declare(strict_types=1);

namespace MigrationModule\Application\GoLive;

final class CommunicationTemplateEngine
{
    /** @var array<string,string> */
    private array $templates = [
        't_minus_7_days' => 'Cutover window {{window}}. Expected downtime: {{expected_downtime}}. Affected modules: {{affected_modules}}. Support: {{support_contact}}.',
        't_minus_1_day' => 'Reminder: go-live starts at {{window}}. Business owner: {{business_owner}}. Next update: {{next_update_time}}.',
        'freeze_start' => 'Freeze is active. Affected modules: {{affected_modules}}. Emergency bypass through support {{support_contact}}.',
        'go_live_started' => 'Go-live has started. Planned downtime {{expected_downtime}}. Next update {{next_update_time}}.',
        'cutover_delayed' => 'Cutover delayed. New update at {{next_update_time}}. Contact {{support_contact}}.',
        'rollback_initiated' => 'Rollback initiated by {{business_owner}}. Affected modules: {{affected_modules}}.',
        'go_live_completed' => 'Go-live completed. Stabilization in progress. Support {{support_contact}}.',
        'stabilization_ongoing' => 'Stabilization ongoing. Next report at {{next_update_time}}.',
        'issue_acknowledged' => 'Issue acknowledged. Owner {{business_owner}}. Next update {{next_update_time}}.',
    ];

    /** @param array<string,string> $vars */
    public function render(string $templateKey, array $vars): string
    {
        $template = $this->templates[$templateKey] ?? 'Template not found';
        foreach ($vars as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }

        return $template;
    }
}

<?php

namespace App\Models;

class MembershipApplication extends BaseModel
{
    protected $primaryKey = 'application_id';

    public const ID_TYPES = [
        'Philippine National ID (PhilSys)',
        "Driver's License",
        'Passport',
        'UMID',
        'Postal ID',
        "Voter's ID",
        'PRC ID',
        'Senior Citizen ID',
        'PWD ID',
        'Other government-issued ID',
    ];

    public const RESIDENCE_TYPES = [
        'owned'  => 'Owned',
        'rented' => 'Rented',
        'shared' => 'Shared / boarding',
        'other'  => 'Other',
    ];

    /** Short explanations shown by the (i) help button next to "Type of Residence". */
    public const RESIDENCE_HELP = [
        'owned'  => 'The house belongs to you or your family.',
        'rented' => 'You pay rent to the owner of the house.',
        'shared' => 'You live in someone else\'s house or a boarding house with others.',
        'other'  => 'None of the above (e.g. caretaker, staff housing).',
    ];

    /** Applications from one IP address per hour — keeps the public form from flooding the queue or the mailer. */
    public const MAX_PER_IP_PER_HOUR = 5;

    /**
     * Progress checklist shown on the applicant status page. Each step is
     * ['label' => ..., 'state' => done|pending|failed|waiting].
     */
    public function progress(): array
    {
        $status = $this->status;
        $rejected = $status === 'rejected';
        $approved = in_array($status, ['approved', 'active'], true);
        $emailDone = !empty($this->email_verified_at);
        $submitted = !empty($this->submitted_at);

        return [
            ['label' => 'Email Verification', 'state' => $emailDone ? 'done' : 'pending'],
            ['label' => 'Application Submitted', 'state' => $submitted ? 'done' : ($emailDone ? 'pending' : 'waiting')],
            ['label' => 'Admin Review', 'state' => match (true) {
                $rejected => 'failed',
                $approved => 'done',
                $status === 'pending_review' => 'pending',
                default => 'waiting',
            }],
            ['label' => 'Account Approval', 'state' => match (true) {
                $rejected => 'failed',
                $approved => 'done',
                default => 'waiting',
            }],
            ['label' => 'Meter Assignment', 'state' => match (true) {
                $status === 'active' => 'done',
                $status === 'approved' => 'pending',
                default => 'waiting',
            }],
            ['label' => 'Account Activation', 'state' => $status === 'active' ? 'done' : 'waiting'],
        ];
    }
}

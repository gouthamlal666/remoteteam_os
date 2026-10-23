<?php

namespace Database\Seeders;

use App\Enums\ProjectStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Activity;
use App\Models\Conversation;
use App\Models\DailyReport;
use App\Models\Department;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\ChatService;
use App\Services\Notifier;
use Illuminate\Database\Seeder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Demo organisation. Every account uses the password "password".
 *   sijojorge@gmail.com  Super Admin
 *   mebijose@admin.co.uk         Admin (Development)
 *   gouthamlal@developer.co.uk   Member (Laravel Developer)
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(42);

        $super = User::factory()->superAdmin()->create([
            'name' => 'Sijo George', 'email' => 'sijojorge@gmail.com', 'job_title' => 'Founder & CEO',
        ]);

        $departments = collect([
            ['Development', 'indigo', 'Backend, frontend and mobile engineering.'],
            ['Design', 'rose', 'Product, UI and brand design.'],
            ['Marketing', 'amber', 'Campaigns, SEO, content and social.'],
            ['Video Editing', 'violet', 'Video production and post-production.'],
            ['SEO', 'emerald', 'Search engine optimisation, keywords and site audits.'],
            ['Client Relations', 'sky', 'Client communication, onboarding and account management.'],
            ['Human Resources', 'rose', 'Hiring, onboarding, employee records and people operations.'],
            ['Accounts', 'amber', 'Invoicing, payroll, expenses and financial reporting.'],
        ])->mapWithKeys(fn ($d) => [$d[0] => Department::create([
            'name' => $d[0], 'slug' => Str::slug($d[0]), 'color' => $d[1], 'description' => $d[2],
        ])]);

        $admins = collect([
            ['Mebi Jose', 'mebijose@admin.co.uk', 'Engineering Manager', 'Development'],
            ['Meera Nair', 'meera@remoteteam.test', 'Design Lead', 'Design'],
            ['David Chen', 'david@remoteteam.test', 'Marketing Manager', 'Marketing'],
        ])->mapWithKeys(function ($a) use ($departments) {
            $admin = User::factory()->admin()->create([
                'name' => $a[0], 'email' => $a[1], 'job_title' => $a[2], 'department_id' => $departments[$a[3]]->id,
            ]);
            $departments[$a[3]]->update(['manager_id' => $admin->id]);

            return [$a[3] => $admin];
        });
        // Video Editing is run by the super admin directly.
        $admins['Video Editing'] = $super;

        $members = collect([
            ['Goutham Lal', 'rahul', 'Laravel Developer', 'Development', ['Laravel', 'PHP', 'MySQL', 'Vue', 'Git', 'Docker'], 'gouthamlal@developer.co.uk'],
            ['Priya Sharma', 'priya', 'Frontend Developer', 'Development', ['Tailwind CSS', 'Alpine.js', 'Livewire', 'TypeScript']],
            ['Arjun Das', 'arjun', 'Mobile Developer', 'Development', ['Flutter', 'Dart', 'Firebase']],
            ['Fatima Khan', 'fatima', 'QA Engineer', 'Development', ['Pest', 'Playwright', 'Testing']],
            ['Lena Fischer', 'lena', 'UI Designer', 'Design', ['Figma', 'Design Systems', 'Prototyping']],
            ['Kiran Raj', 'kiran', 'Graphic Designer', 'Design', ['Illustrator', 'Photoshop', 'Branding']],
            ['Sofia Rossi', 'sofia', 'SEO Specialist', 'Marketing', ['SEO', 'Ahrefs', 'Analytics']],
            ['Omar Haddad', 'omar', 'Content Writer', 'Marketing', ['Copywriting', 'Blogging', 'Email']],
            ['Neha Gupta', 'neha', 'Social Media Marketer', 'Marketing', ['Instagram', 'LinkedIn', 'Meta Ads']],
            ['Vishnu Prasad', 'vishnu', 'Video Editor', 'Video Editing', ['Premiere Pro', 'After Effects', 'DaVinci Resolve']],
            ['Ana Lima', 'ana', 'Motion Designer', 'Video Editing', ['After Effects', 'Cinema 4D']],
        ])->mapWithKeys(fn ($m) => [$m[1] => User::factory()->create([
            'name' => $m[0], 'email' => $m[5] ?? "{$m[1]}@remoteteam.test", 'job_title' => $m[2],
            'department_id' => $departments[$m[3]]->id, 'skills' => $m[4],
        ])]);

        User::factory()->inactive()->create([
            'name' => 'Former Contractor', 'email' => 'former@remoteteam.test',
            'job_title' => 'Freelance Developer', 'department_id' => $departments['Development']->id,
        ]);

        // [project name, client, department, status, colour, start offset (days), deadline offset, members, tasks]
        // task: [title, assignee, priority, status, deadline offset, hours, completed offset (null = on deadline), revisions]
        $plan = [
            ['Client Website', 'Acme Corp', 'Development', ProjectStatus::Active, 'indigo', -40, 20, ['rahul', 'priya', 'fatima', 'lena', 'sofia'], [
                ['Build Laravel API authentication', 'rahul', 'high', 'in_progress', 7, 12, null, 0],
                ['Set up CI pipeline', 'rahul', 'medium', 'completed', -20, 4, -22, 0],
                ['Homepage UI implementation', 'priya', 'high', 'review', 2, 10, null, 1],
                ['Contact form with validation', 'priya', 'medium', 'completed', -10, 5, -9, 0],
                ['Design homepage & landing sections', 'lena', 'high', 'completed', -25, 16, -26, 1],
                ['Regression test suite', 'fatima', 'medium', 'todo', 14, 8, null, 0],
                ['On-page SEO audit', 'sofia', 'medium', 'revision', 1, 6, null, 2],
                ['Database schema & migrations', 'rahul', 'urgent', 'completed', -30, 6, -31, 0],
                ['Blog module', 'rahul', 'low', 'todo', -2, 10, null, 0],
            ]],
            ['E-commerce Website', 'ShopNest', 'Development', ProjectStatus::Active, 'emerald', -60, 35, ['rahul', 'priya', 'arjun', 'fatima', 'kiran', 'neha'], [
                ['Product catalogue & filters', 'priya', 'high', 'in_progress', 5, 14, null, 0],
                ['Stripe checkout integration', 'rahul', 'urgent', 'todo', 10, 16, null, 0],
                ['Cart & wishlist', 'priya', 'medium', 'completed', -15, 8, -13, 1],
                ['Order management admin', 'rahul', 'high', 'in_progress', -1, 12, null, 0],
                ['Product photography edits', 'kiran', 'medium', 'completed', -35, 10, -36, 0],
                ['Launch social campaign', 'neha', 'medium', 'todo', 30, 12, null, 0],
                ['Checkout flow QA', 'fatima', 'high', 'todo', 12, 6, null, 0],
                ['Performance optimisation', 'arjun', 'low', 'completed', -5, 6, -6, 0],
            ]],
            ['Mobile App', 'Internal', 'Development', ProjectStatus::Planning, 'sky', -5, 90, ['arjun', 'lena', 'rahul'], [
                ['App architecture spike', 'arjun', 'high', 'in_progress', 6, 8, null, 0],
                ['Onboarding screens design', 'lena', 'medium', 'todo', 15, 12, null, 0],
                ['Push notification service', 'rahul', 'medium', 'todo', 25, 8, null, 0],
            ]],
            ['Brand Refresh', 'Internal', 'Design', ProjectStatus::Active, 'rose', -30, 15, ['lena', 'kiran', 'omar'], [
                ['New logo explorations', 'kiran', 'high', 'completed', -18, 10, -17, 2],
                ['Brand guidelines document', 'lena', 'medium', 'review', 3, 12, null, 0],
                ['Website copy refresh', 'omar', 'medium', 'in_progress', 6, 8, null, 0],
                ['Social templates', 'kiran', 'low', 'todo', -3, 6, null, 0],
            ]],
            ['Q4 Growth Campaign', 'Internal', 'Marketing', ProjectStatus::Active, 'amber', -20, 40, ['sofia', 'omar', 'neha', 'vishnu'], [
                ['Keyword research', 'sofia', 'high', 'completed', -12, 6, -12, 0],
                ['Write 6 pillar blog posts', 'omar', 'high', 'in_progress', 9, 24, null, 0],
                ['LinkedIn ad creatives', 'neha', 'medium', 'review', 0, 8, null, 0],
                ['Email nurture sequence', 'omar', 'medium', 'completed', -4, 10, -2, 1],
                ['Campaign teaser video', 'vishnu', 'medium', 'todo', 8, 10, null, 0],
            ]],
            ['Product Launch Video', 'ShopNest', 'Video Editing', ProjectStatus::Active, 'violet', -15, 10, ['vishnu', 'ana', 'omar'], [
                ['Script & storyboard', 'omar', 'high', 'completed', -10, 6, -11, 0],
                ['Rough cut', 'vishnu', 'high', 'completed', -3, 12, -1, 1],
                ['Motion graphics pack', 'ana', 'medium', 'in_progress', 4, 14, null, 0],
                ['Final colour grade & export', 'vishnu', 'high', 'todo', 9, 6, null, 0],
            ]],
            ['Company Intranet v1', 'Internal', 'Development', ProjectStatus::Completed, 'slate', -120, -30, ['rahul', 'priya'], [
                ['Employee directory', 'rahul', 'medium', 'completed', -60, 8, -61, 0],
                ['Announcements feed', 'priya', 'low', 'completed', -45, 6, -44, 0],
            ]],
        ];

        foreach ($plan as [$name, $client, $dept, $status, $color, $start, $end, $team, $tasks]) {
            $owner = $admins[$dept];
            $project = Project::forceCreate([
                'name' => $name,
                'client_id' => $client === 'Internal' ? null : \App\Models\Client::firstOrCreate(['name' => $client], ['status' => 'active', 'industry' => 'E-commerce', 'website' => 'https://'.\Illuminate\Support\Str::slug($client).'.example', 'client_since' => today()->subMonths(8)])->id,
                'department_id' => $departments[$dept]->id,
                'owner_id' => $owner->id, 'status' => $status, 'color' => $color,
                'description' => "{$name} for {$client}. Tracks all work across design, development and marketing.",
                'start_date' => today()->addDays($start), 'deadline' => today()->addDays($end),
                'created_at' => now()->addDays($start),
            ]);
            $project->members()->sync(collect($team)->map(fn ($k) => $members[$k]->id));
            $this->activity($owner, $project, 'project.created', "created project {$name}", now()->addDays($start));

            foreach ($tasks as [$title, $assignee, $priority, $taskStatus, $deadline, $hours, $completedOffset, $revisions]) {
                $member = $members[$assignee];
                $status = TaskStatus::from($taskStatus);
                $createdAt = now()->addDays(min(-1, max($start, $deadline - 14)))->setTime(9, 0);
                $completedAt = $status === TaskStatus::Completed
                    ? today()->addDays($completedOffset ?? $deadline)->setTime(16, 30) : null;

                $task = Task::forceCreate([
                    'project_id' => $project->id, 'created_by' => $owner->id, 'title' => $title,
                    'description' => "Scope: {$title}.\n\nAcceptance criteria are in the linked brief. Ask in the discussion if anything is unclear.",
                    'status' => $status, 'priority' => TaskPriority::from($priority),
                    'start_date' => $createdAt->toDateString(), 'deadline' => today()->addDays($deadline),
                    'estimated_hours' => $hours, 'revision_count' => $revisions,
                    'submitted_at' => in_array($status, [TaskStatus::Review, TaskStatus::Completed]) ? ($completedAt ?? now()->subHours(5)) : null,
                    'completed_at' => $completedAt, 'created_at' => $createdAt,
                ]);
                $task->assignees()->attach($member->id);

                $this->activity($owner, $task, 'task.created', "created task \"{$title}\"", $createdAt);
                if ($status !== TaskStatus::Todo) {
                    $this->activity($member, $task, 'task.status', "changed status of \"{$title}\" → In Progress", $createdAt->copy()->addHours(20));
                }
                if (in_array($status, [TaskStatus::Review, TaskStatus::Revision, TaskStatus::Completed])) {
                    $submitted = $completedAt?->copy()->subHours(3) ?? now()->subHours(5);
                    $task->comments()->forceCreate(['user_id' => $member->id, 'type' => 'submission', 'body' => 'Ready for review — details and files attached.', 'created_at' => $submitted]);
                    $this->activity($member, $task, 'task.submitted', "submitted \"{$title}\" for review", $submitted);
                }
                if ($status === TaskStatus::Revision) {
                    $task->comments()->forceCreate(['user_id' => $owner->id, 'type' => 'revision', 'body' => 'Close! Please address the feedback in the brief and resubmit.', 'created_at' => now()->subHours(2)]);
                    $this->activity($owner, $task, 'task.revision', "sent \"{$title}\" back for revision", now()->subHours(2));
                }
                if ($status === TaskStatus::Completed) {
                    $task->comments()->forceCreate(['user_id' => $owner->id, 'type' => 'approval', 'body' => 'Looks great, thanks!', 'created_at' => $completedAt]);
                    $this->activity($owner, $task, 'task.approved', "approved \"{$title}\"", $completedAt);
                }
                if ($status === TaskStatus::InProgress && mt_rand(0, 1)) {
                    $task->comments()->forceCreate(['user_id' => $member->id, 'type' => 'comment', 'body' => 'Making good progress, should be on track for the deadline.', 'created_at' => now()->subHours(mt_rand(1, 30))]);
                }
            }
        }

        $this->seedRemoteProfiles();
        $this->seedEmployeeRecords();
        $this->seedChecklistsAndTime($members);
        $this->seedChat($super, $admins, $members, $departments);
        $this->seedMeetings($super, $admins, $members);
        $this->seedReports($members);
        $this->seedNotifications($admins, $members);
    }

    private function seedRemoteProfiles(): void
    {
        $zones = [
            'lena' => 'Europe/Berlin', 'david' => 'America/New_York', 'sofia' => 'Europe/Rome',
            'ana' => 'America/Sao_Paulo', 'omar' => 'Asia/Dubai', 'kiran' => 'Asia/Singapore',
        ];

        User::query()->each(function (User $u) use ($zones) {
            $handle = Str::before($u->email, '@');
            $u->forceFill([
                'timezone' => $zones[$handle] ?? 'Asia/Kolkata',
                'work_start' => '09:00', 'work_end' => '18:00',
                'last_seen_at' => mt_rand(0, 1) ? now()->subMinutes(mt_rand(0, 3)) : now()->subHours(mt_rand(2, 30)),
            ])->save();
        });

        $statuses = [
            'arjun' => ['🎯', 'Focusing on the mobile spike', now()->addHours(2)],
            'kiran' => ['🌴', 'On leave until Monday', now()->addDays(4)],
            'meera' => ['📅', 'In client workshops', now()->addHours(3)],
        ];
        foreach ($statuses as $handle => [$emoji, $text, $until]) {
            User::where('email', "{$handle}@remoteteam.test")->update(['status_emoji' => $emoji, 'status_text' => $text, 'status_expires_at' => $until]);
        }
    }

    private function seedEmployeeRecords(): void
    {
        $cities = [
            'Asia/Kolkata' => ['Kochi', 'Kerala', 'India', '682001', '+91'],
            'Europe/Berlin' => ['Berlin', 'Berlin', 'Germany', '10115', '+49'],
            'America/New_York' => ['New York', 'NY', 'USA', '10001', '+1'],
            'Europe/Rome' => ['Rome', 'Lazio', 'Italy', '00118', '+39'],
            'America/Sao_Paulo' => ['São Paulo', 'SP', 'Brazil', '01000-000', '+55'],
            'Asia/Dubai' => ['Dubai', 'Dubai', 'UAE', '00000', '+971'],
            'Asia/Singapore' => ['Singapore', 'Singapore', 'Singapore', '018956', '+65'],
        ];
        $relations = ['Father', 'Mother', 'Spouse', 'Brother', 'Sister'];

        User::orderBy('id')->get()->each(function (User $u, int $i) use ($cities, $relations) {
            [$city, $state, $country, $post, $code] = $cities[$u->timezone] ?? $cities['Asia/Kolkata'];
            $u->forceFill(['phone' => $code.' '.(9000000000 + $u->id * 7919)])->save();

            $u->employeeProfile()->create([
                'employee_code' => sprintf('RT-%04d', $u->id),
                'employment_type' => $u->email === 'former@remoteteam.test' ? 'contract' : 'full_time',
                'date_of_joining' => today()->subDays(90 + $u->id * 47),
                'date_of_birth' => today()->subYears(24 + $u->id % 15)->subDays($u->id * 13),
                'gender' => null,
                'blood_group' => ['O+', 'A+', 'B+', 'AB+', 'O-', 'A-'][$i % 6],
                'address_line' => (10 + $u->id).' Demo Street',
                'city' => $city, 'state' => $state, 'country' => $country, 'postal_code' => $post,
                'guardian_name' => 'Guardian of '.Str::before($u->name, ' '),
                'guardian_relation' => $relations[$i % 5],
                'guardian_phone' => $code.' '.(8000000000 + $u->id * 6271),
            ]);
        });
    }

    private function seedChecklistsAndTime($members): void
    {
        $checklists = [
            'Build Laravel API authentication' => [['Login endpoint', true], ['Register endpoint', true], ['Password reset', false], ['Rate limiting', false], ['Feature tests', true]],
            'Homepage UI implementation' => [['Hero section', true], ['Features grid', true], ['Testimonials', true], ['Mobile QA', true]],
            'Product catalogue & filters' => [['Category filter', true], ['Price range', false], ['Sort options', false]],
            'Write 6 pillar blog posts' => [['Post 1', true], ['Post 2', true], ['Post 3', false], ['Post 4', false], ['Post 5', false], ['Post 6', false]],
        ];
        foreach ($checklists as $title => $items) {
            $task = Task::where('title', $title)->first();
            foreach ($items as $i => [$item, $done]) {
                $task?->checklistItems()->create(['title' => $item, 'is_done' => $done, 'position' => $i]);
            }
        }

        // Time logged on open and recently completed tasks by their assignees.
        Task::with('assignees')->whereIn('status', ['in_progress', 'review', 'revision', 'completed'])->get()->each(function (Task $task) {
            foreach ($task->assignees as $assignee) {
                $budget = (int) ($task->estimated_hours * 60 * mt_rand(40, 110) / 100);
                $day = 0;
                while ($budget > 0 && $day < 8) {
                    $minutes = min($budget, mt_rand(45, 180));
                    $start = today()->subDays($day + ($task->completed_at ? max(0, (int) $task->completed_at->diffInDays(today())) : 0))->setTime(mt_rand(9, 14), [0, 15, 30][mt_rand(0, 2)]);
                    if ($start->isFuture()) {
                        $start = now()->subHours(3);
                    }
                    $assignee->timeEntries()->create(['task_id' => $task->id, 'started_at' => $start, 'ended_at' => $start->copy()->addMinutes($minutes), 'minutes' => $minutes]);
                    $budget -= $minutes;
                    $day++;
                }
            }
        });

        // Rahul has a timer running right now.
        $auth = Task::where('title', 'Build Laravel API authentication')->first();
        $members['rahul']->timeEntries()->create(['task_id' => $auth->id, 'started_at' => now()->subMinutes(42)]);
    }

    private function seedNotifications($admins, $members): void
    {
        $notifier = app(Notifier::class);
        $dev = $admins['Development'];

        $notifier->taskAssigned(Task::where('title', 'Stripe checkout integration')->first(), [$members['rahul']], $dev);
        $notifier->taskAssigned(Task::where('title', 'Order management admin')->first(), [$members['rahul']], $dev);
        $notifier->meetingInvited(Meeting::where('title', 'All-hands monthly sync')->first(), User::active()->get(), User::where('email', 'sijojorge@gmail.com')->first());
        $homepage = Task::where('title', 'Homepage UI implementation')->first();
        $notifier->taskSubmitted($homepage->load('creator', 'project.owner'), $members['priya']);
        $seo = Task::where('title', 'On-page SEO audit')->first();
        $notifier->taskReviewed($seo->load('assignees'), $dev, false, 'Please cover the blog pages too.');
        $notifier->taskCommented($homepage->load('assignees', 'creator'), $dev, '@priya.sharma nice work — can @goutham.lal pair on the API bits?');

        // Older ones are already read.
        DatabaseNotification::query()->update(['created_at' => now()->subMinutes(mt_rand(5, 300))]);
        foreach (User::all() as $u) {
            $u->notifications()->latest()->skip(2)->take(100)->get()->each->markAsRead();
        }
    }

    private function seedChat(User $super, $admins, $members, $departments): void
    {
        $everyone = User::active()->pluck('id')->all();
        $general = Conversation::create(['type' => Conversation::CHANNEL, 'name' => 'General', 'description' => 'Company-wide announcements and chatter', 'created_by' => $super->id]);
        $general->members()->sync($everyone);

        $channels = ['general' => $general];
        foreach (['Development' => 'Development Team', 'Design' => 'Design Team', 'Marketing' => 'Marketing Team'] as $dept => $name) {
            $channel = Conversation::create(['type' => Conversation::CHANNEL, 'name' => $name, 'created_by' => $admins[$dept]->id]);
            $channel->members()->sync(User::active()->where('department_id', $departments[$dept]->id)->pluck('id')->push($super->id)->all());
            $channels[$dept] = $channel;
        }
        $alpha = Conversation::create(['type' => Conversation::CHANNEL, 'name' => 'Project Alpha', 'description' => 'Client Website launch squad', 'created_by' => $admins['Development']->id]);
        $alpha->members()->sync(Project::where('name', 'Client Website')->first()->members()->pluck('users.id')->push($admins['Development']->id)->all());
        $channels['alpha'] = $alpha;

        $say = function (Conversation $c, User $u, string $body, int $minutesAgo, array $extra = []) {
            $m = $c->messages()->forceCreate(['user_id' => $u->id, 'body' => $body, 'created_at' => now()->subMinutes($minutesAgo), 'updated_at' => now()->subMinutes($minutesAgo)] + $extra);
            $c->forceFill(['last_message_at' => $m->created_at])->save();

            return $m;
        };

        $welcome = $say($general, $super, 'Welcome to RemoteTeam OS 🎉 Use this channel for company-wide updates. Daily reports are due by 7 PM, please.', 60 * 26, ['pinned_at' => now()->subDay(), 'pinned_by' => $super->id]);
        $welcome->reactions()->createMany([['user_id' => $members['rahul']->id, 'emoji' => '🎉'], ['user_id' => $members['priya']->id, 'emoji' => '🎉'], ['user_id' => $admins['Development']->id, 'emoji' => '👍']]);
        $say($general, $admins['Marketing'], 'Q4 campaign kicks off next week — big thanks to @sofia.rossi for the keyword research!', 60 * 5);
        $say($general, $members['sofia'], 'Happy to help! 🙌', 60 * 5 - 3);

        $q = $say($channels['Development'], $members['priya'], 'Anyone free to review the homepage PR? It is ready for review in the task too.', 180);
        $say($channels['Development'], $members['rahul'], 'On it after lunch 👍', 170, ['reply_to_id' => $q->id]);
        $say($channels['Development'], $admins['Development'], '@goutham.lal how is the auth API looking? Client demo is on Friday.', 95);
        $say($channels['Development'], $members['rahul'], 'Login + register are done, working on password reset now. Should be ready Thursday.', 90);

        $say($alpha, $members['lena'], 'Uploaded the final landing section designs: https://figma.com/file/demo-client-website', 60 * 30);
        $say($alpha, $members['sofia'], 'SEO audit revision coming today, sorry for the delay.', 40);

        $say($channels['Design'], $admins['Design'], 'Brand guidelines draft is in review — comments by Wednesday please.', 60 * 8);

        $chat = app(ChatService::class);
        $dm = $chat->directBetween($admins['Development'], $members['rahul']);
        $say($dm, $admins['Development'], 'Hey Rahul, quick one — can you take the Stripe checkout task on E-commerce after auth is done?', 60 * 3);
        $say($dm, $members['rahul'], 'Sure! I will start it Monday.', 60 * 3 - 10);
        $say($dm, $admins['Development'], 'Perfect, thanks 🙏', 60 * 3 - 12);

        $dm2 = $chat->directBetween($members['rahul'], $members['priya']);
        $say($dm2, $members['priya'], 'Are you using Sanctum or Passport for the API?', 60 * 20);
        $say($dm2, $members['rahul'], 'Sanctum — much simpler for our SPA + mobile use case.', 60 * 20 - 5);

        // Everyone has read things up to a few hours ago, so there are some unread messages.
        foreach (Conversation::with('members')->get() as $c) {
            foreach ($c->members as $member) {
                $c->members()->updateExistingPivot($member->id, ['last_read_at' => now()->subHours(4)]);
            }
        }
    }

    private function seedMeetings(User $super, $admins, $members): void
    {
        $dev = $admins['Development'];
        $devTeam = collect(['rahul', 'priya', 'arjun', 'fatima'])->map(fn ($k) => $members[$k]->id);

        // Past weekly dev meetings with attendance taken.
        foreach ([21, 14, 7] as $i => $daysAgo) {
            $meeting = Meeting::create([
                'organizer_id' => $dev->id, 'title' => 'Weekly Development Meeting',
                'agenda' => "1. Sprint review\n2. Blockers\n3. Plan for next week",
                'starts_at' => today()->subDays($daysAgo)->setTime(10, 0), 'duration_minutes' => 30,
                'meeting_link' => 'https://meet.google.com/abc-defg-hij', 'status' => Meeting::COMPLETED,
                'notes' => "- Sprint on track\n- Rahul to finish auth API\n- Priya to pair with Fatima on QA",
            ]);
            $meeting->participants()->attach($dev->id, ['response' => 'accepted', 'attended' => true]);
            foreach ($devTeam as $j => $id) {
                // Arjun missed one, Fatima missed one.
                $attended = ! (($j === 2 && $i === 1) || ($j === 3 && $i === 2));
                $meeting->participants()->attach($id, ['response' => 'accepted', 'attended' => $attended]);
            }
        }

        $upcoming = [
            [$dev, 'Weekly Development Meeting', today()->setTime(max(now()->hour + 1, 10) % 24, 0), 30, $devTeam->all(), "1. Sprint review\n2. Blockers\n3. Client demo prep"],
            [$admins['Design'], 'Brand guidelines review', today()->addDay()->setTime(15, 0), 45, [$members['lena']->id, $members['kiran']->id, $members['omar']->id], 'Walk through the brand guidelines draft and agree on final logo.'],
            [$admins['Marketing'], 'Q4 campaign kickoff', today()->addDays(3)->setTime(11, 30), 60, [$members['sofia']->id, $members['omar']->id, $members['neha']->id, $members['vishnu']->id, $dev->id], 'Goals, channels, timeline and owners for Q4.'],
            [$super, 'All-hands monthly sync', today()->addDays(6)->setTime(17, 0), 60, User::active()->pluck('id')->all(), 'Company updates, wins of the month, Q&A.'],
        ];
        foreach ($upcoming as [$organizer, $title, $at, $duration, $people, $agenda]) {
            $meeting = Meeting::create([
                'organizer_id' => $organizer->id, 'title' => $title, 'agenda' => $agenda, 'starts_at' => $at,
                'duration_minutes' => $duration, 'meeting_link' => 'https://meet.google.com/xyz-'.Str::lower(Str::random(4)).'-abc',
            ]);
            $meeting->participants()->attach($organizer->id, ['response' => 'accepted']);
            foreach (array_diff($people, [$organizer->id]) as $n => $id) {
                $meeting->participants()->attach($id, ['response' => ['accepted', 'pending', 'accepted', 'declined'][$n % 4]]);
            }
        }

        $cancelled = Meeting::create([
            'organizer_id' => $dev->id, 'title' => 'Mobile app estimation', 'starts_at' => today()->addDays(2)->setTime(14, 0),
            'duration_minutes' => 30, 'status' => Meeting::CANCELLED,
        ]);
        $cancelled->participants()->attach([$dev->id => ['response' => 'accepted'], $members['arjun']->id => ['response' => 'pending']]);
    }

    private function seedReports($members): void
    {
        $samples = [
            'rahul' => ['Built login and register endpoints with Sanctum; wrote feature tests.', 'Password reset flow', 'Waiting on SMTP credentials for staging.'],
            'priya' => ['Finished homepage hero + features sections, responsive fixes.', 'Pricing section', null],
            'arjun' => ['Architecture spike: compared Flutter state management options.', 'Navigation setup', null],
            'fatima' => ['Wrote regression checklist for checkout.', 'Automating smoke tests', 'Need test Stripe keys.'],
            'lena' => ['Brand guidelines: typography and colour pages.', 'Iconography page', null],
            'sofia' => ['On-page SEO fixes for 12 pages.', 'Meta descriptions', null],
            'omar' => ['Drafted 2 pillar blog posts.', 'Post #3 outline', null],
            'vishnu' => ['Rough cut v2 exported for review.', 'Colour grade', 'Waiting for final voice-over.'],
        ];

        foreach ($samples as $handle => [$done, $wip, $blocker]) {
            for ($d = 13; $d >= 0; $d--) {
                $date = today()->subDays($d);
                // Weekdays only; skip some days (and today for a few people) so "missing" shows up.
                if ($date->isWeekend() || (mt_rand(1, 10) <= 2) || ($d === 0 && in_array($handle, ['arjun', 'omar']))) {
                    continue;
                }
                DailyReport::create([
                    'user_id' => $members[$handle]->id, 'report_date' => $date->toDateString(),
                    'completed' => $done, 'in_progress' => $wip,
                    'blockers' => $d < 3 ? $blocker : null,
                    'hours_worked' => [6.5, 7, 7.5, 8, 8][mt_rand(0, 4)],
                ]);
            }
        }
    }

    private function activity(User $user, Project|Task $subject, string $action, string $description, Carbon $at): void
    {
        Activity::create([
            'user_id' => $user->id,
            'project_id' => $subject instanceof Project ? $subject->id : $subject->project_id,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->id,
            'action' => $action,
            'description' => $description,
            'created_at' => $at->isFuture() ? now() : $at,
        ]);
    }
}

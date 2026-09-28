# RemoteTeam OS

Remote team management built on the TALL stack: Laravel 13, Livewire 4, Alpine.js (bundled with Livewire), Tailwind CSS 4, and MySQL.

## Setup

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate   # then set DB_* (see below)
php artisan migrate:fresh --seed
npm run build            # or `npm run dev` while working on the UI
php artisan serve
```

### Database (XAMPP MariaDB)

Locally the app uses the XAMPP MariaDB that phpMyAdmin shows. Database `remoteteamos_db` is reached through XAMPP's socket, because Homebrew MySQL already holds port 3306:

```
DB_CONNECTION=mariadb
DB_SOCKET=/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock
DB_DATABASE=remoteteamos_db
DB_USERNAME=root
DB_PASSWORD=
```

`php artisan migrate:fresh --seed` resets all data and signs everyone out, because sessions are stored in the database.

Demo accounts (password `password`):

| Email | Role |
|---|---|
| sijojorge@gmail.com | Super Admin (Sijo George). Its password was changed in the live database; re-seeding resets it to `password` |
| mebijose@admin.co.uk | Admin, Development (Mebi Jose) |
| meera@remoteteam.test / david@remoteteam.test | Admin (Design / Marketing) |
| gouthamlal@developer.co.uk | Member, Laravel Developer (Goutham Lal) |

## What's built

- **Roles**: `super_admin`, `admin`, `member` on `users.role`, enforced by policies in `app/Policies`.
- **Departments**: super admin CRUD, with a manager (admin) per department.
- **People**: super admins manage everyone; admins manage members in their own department.
- **Projects**: progress %, members, board by status, activity.
- **Tasks**: assignees, priority, dates, estimate, attachments, comments, and the review workflow
  `todo → in_progress → review → completed`, where `review` can go to `revision` and back to `in_progress`.
  *Overdue* is derived (deadline passed and not completed), not stored.
- **Activity timeline**: every task and project change goes through `TaskService` / `ActivityLogger`.
- **Dashboards** per role, and department completion and on-time rates from `PerformanceService`.
- **Chat** (`/chat`): team channels and one-to-one messages, with emoji, file and image uploads, replies, @mentions with autocomplete, reactions, pins, search and unread counts.
  Conversations are private to their members, and not even a super admin can read a chat they aren't in.
  Messages refresh by polling every 4 seconds. For true real-time, add Laravel Reverb and Echo.
- **Meetings** (`/meetings`): upcoming list, month calendar and history. Admins schedule meetings and invite people, individually or a whole department at once.
  Invitees answer Yes or No. The Join button becomes active 15 minutes before the start.
  Organizers write notes, take attendance and mark the meeting completed.
- **Reports** (`/reports`): daily work reports, which can be edited for the last 14 days.
  Managers also get team daily (who submitted and who is missing), a printable weekly team report, and a performance table plus a monthly trend.
  Meeting attendance counts toward performance.

Visibility: super admins see everything. Admins see projects they own, belong to, or that are in their department. Members see projects they belong to.

### Collaboration essentials

These are modeled on what Asana, ClickUp, Jira, Linear, Slack and Toggl do well:

- **Notifications**: a bell with unread count, a `/notifications` page, and desktop pop-ups (enable them from the bell).
  Emails go out for important events (assignment, review result, due tomorrow, meeting invite, reminder or cancellation), and each person can opt out in Settings.
  People are never notified about their own actions, and direct messages notify once until they are read.
- **Scheduled reminders**: meetings starting within 15 minutes, and tasks due tomorrow. Run `php artisan schedule:work` locally.
  In production, run `schedule:run` from cron every minute.
- **Remote-first profiles**: timezone, each teammate's local time, working hours (shows "working" or "off hours"), a custom status that clears itself, and online presence.
  These appear on profiles, the people list and chat. A **Message** button opens a DM from any profile.
- **Checklists (subtasks)** on tasks, with progress shown on board cards.
- **Time tracking**: a start/stop timer (one running at a time, shown live in the top bar) and manual entries.
  Logged time is compared with the estimate, and weekly reports show reported vs tracked hours.
  Tracking is self-reported only: no screenshots or activity monitoring.
- **Command palette**: Ctrl/⌘ K searches tasks, projects, people and meetings you're allowed to see.
- **Drag-and-drop board**: managers can move cards to any column. Assignees can move their own cards between To Do and In Progress.
- **Settings** (`/settings`): profile, status, timezone, working hours, email preference and password change.
- **Forgot / reset password**, plus sign-ins recorded in the activity log.

### Employee records & deletion

- **Employee record** on each profile (super admins edit it; the employee can view their own):
  - photo, employee ID, employment type and joining date
  - date of birth, gender, blood group and address
  - mobile, alternate phone and personal email
  - **guardian / emergency contact** (name, relation, phone)
  - HR notes
  - **documents** (ID proof, resume, offer letter, contracts, certificates) with optional expiry dates and "expires soon" / "expired" badges

  Files are stored privately and only served to authorised signed-in users. Admins and colleagues can't see the record, and the audit log records that a change happened but not the personal values.
- **Profile photos** appear everywhere avatars are shown. People can set their own in Settings.
- **Deleting** (super admin): people, meetings, chat channels, comments, daily reports and notifications can be deleted, in addition to departments, projects, tasks, files and time entries.
  Deleting a person transfers their projects, tasks and meetings to you. Their comments, messages, files and logged time stay as "Deleted user". Their personal data and files are removed.
  To keep everything, deactivate the person instead.

### System settings & WhatsApp

**System settings** (`/system-settings`, super admins only):
- **General:** company name and logo (sidebar, sign-in page, emails), and the default timezone for new people.
- **Work & reports:** working days and default hours, plus a daily-report reminder at a set time on working days.
- **Notifications & WhatsApp:** email on/off for everyone, the WhatsApp provider, which alerts go to WhatsApp, a test-message button, and a delivery log.
- **Security:** minimum password length, and whether members may private-message each other.

**WhatsApp notifications:**
- People opt in under *My settings* with their mobile number.
- Providers:
  - `log`: test mode, no account needed
  - **Meta WhatsApp Cloud API**: needs a phone number ID, a permanent token and an approved Utility template with three body variables (title, message, link)
  - **Twilio**: needs an Account SID, an auth token and a WhatsApp sender
- Tokens are stored encrypted and never shown again.
- Messages are sent during the request with an 8-second timeout. At scale, move sending onto a queue worker.

Mail uses the `log` driver locally, so emails are written to `storage/logs/laravel.log`. Set `MAIL_*` in `.env` for real delivery.

## Next phases

Real-time chat with Reverb, Google Meet and Zoom integration, S3 or R2 storage, leave/time-off calendar, task dependencies and recurring tasks, two-factor authentication, and dark mode.

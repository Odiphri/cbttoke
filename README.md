<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## TOKE CBT Portal: Lesson Notes and Exercises

This portal includes a Lesson Notes module for ordinary class notes and exercises. The module is separate from formal CBT exams, so lesson exercises use their own tables instead of `exams`, `questions` or `exam_attempts`.

Teachers can create structured lesson notes for assigned class/subject combinations, organised by the active academic session, term and Week 1 through Week 15. Notes can be saved as drafts, submitted for approval, returned or rejected with review history, approved and published, or archived. Students and prefects only see approved notes for their own class.

Lesson notes may include exercises with multiple-choice, true/false and theory questions. Objective and true/false answers are marked automatically on submission. Theory answers remain awaiting manual marking until the teacher awards marks and feedback. Teachers can configure one, limited or unlimited attempts, choose highest/latest/first counted score, and grant an additional retry to a specific student.

### Workflow

1. Teacher creates or edits a draft note using the active academic session.
2. Teacher submits the note for HOD/admin approval.
3. HOD/admin approves, returns for correction, rejects, or archives approved notes.
4. Approved notes are published to students and prefects in the assigned class.
5. Students take available exercises and view only permitted scores, feedback and correct answers.

### Setup

Run migrations after pulling the feature:

```bash
php artisan migrate
```

Lesson note writing uses the installed TinyMCE rich text editor, including inline image uploads. Attachments, inline lesson images and question images are stored on Laravel's public disk. Ensure the public storage symlink exists:

```bash
php artisan storage:link
```

AI note and exercise generation uses the existing Gemini integration. Add a Gemini key to `.env` before using the AI buttons:

```bash
GEMINI_API_KEY=your-key-here
```

Teachers can generate a full lesson note from the topic/instructions, choose a requested word count up to 1,000,000 words, generate exercise questions from a lesson topic, or upload a PDF source and let AI reshape the extracted text into a proper classroom note. Very large word counts still depend on Gemini's per-response output limits, so the app asks for the longest complete note the model can fit in one response. PDF import uses `smalot/pdfparser`, so the PDF must contain selectable text; scanned image-only PDFs need OCR before upload.

### Test Commands

```bash
php artisan test tests/Feature/LessonNotesModuleTest.php
php artisan test
php artisan migrate:fresh --env=testing
php artisan route:list --name=lesson-notes
php artisan route:list --name=exercises
```

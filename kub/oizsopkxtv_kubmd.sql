-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Хост: localhost:3306
-- Время создания: Дек 20 2025 г., 15:05
-- Версия сервера: 11.4.8-MariaDB-cll-lve
-- Версия PHP: 8.3.27

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `oizsopkxtv_kubmd`
--

-- --------------------------------------------------------

--
-- Структура таблицы `actions`
--

CREATE TABLE `actions` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` longtext NOT NULL,
  `type` enum('action','instruction','price_change','cross_sale') NOT NULL,
  `is_important` tinyint(1) DEFAULT 0,
  `is_required` tinyint(1) DEFAULT 0,
  `created_by` int(11) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `active` tinyint(1) DEFAULT 1,
  `due_at` date DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `delete_after` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `actions`
--

INSERT INTO `actions` (`id`, `title`, `content`, `type`, `is_important`, `is_required`, `created_by`, `created_at`, `active`, `due_at`, `deleted_at`, `delete_after`) VALUES
(1, 'ываыва', '<p>ываываыва</p>', 'action', 1, 1, 1, '2025-12-18 02:57:45', 1, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `action_branches`
--

CREATE TABLE `action_branches` (
  `id` int(11) NOT NULL,
  `action_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `action_branches`
--

INSERT INTO `action_branches` (`id`, `action_id`, `branch_id`) VALUES
(1, 1, 1),
(2, 1, 2);

-- --------------------------------------------------------

--
-- Структура таблицы `action_user_status`
--

CREATE TABLE `action_user_status` (
  `id` int(11) NOT NULL,
  `action_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` enum('viewed','done') NOT NULL,
  `updated_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `attendance_logs`
--

CREATE TABLE `attendance_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `work_date` date NOT NULL,
  `checkin_time` datetime NOT NULL,
  `late_minutes` int(11) NOT NULL DEFAULT 0,
  `penalty_amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `attendance_penalties`
--

CREATE TABLE `attendance_penalties` (
  `id` int(11) NOT NULL,
  `attendance_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` int(11) NOT NULL,
  `minutes` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `attendance_settings`
--

CREATE TABLE `attendance_settings` (
  `id` int(11) NOT NULL,
  `work_start` time NOT NULL DEFAULT '09:00:00',
  `allowed_late_minutes` int(11) NOT NULL DEFAULT 15,
  `block_after_minutes` int(11) NOT NULL DEFAULT 30,
  `token_lifetime_minutes` int(11) NOT NULL DEFAULT 10,
  `enable_penalties` tinyint(1) NOT NULL DEFAULT 0,
  `penalty_per_minute` decimal(10,2) NOT NULL DEFAULT 0.00,
  `max_penalty_per_day` decimal(10,2) NOT NULL DEFAULT 0.00,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `start_time` time NOT NULL DEFAULT '09:00:00',
  `max_checkin_minutes` int(11) NOT NULL DEFAULT 120,
  `allow_manual` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `attendance_settings`
--

INSERT INTO `attendance_settings` (`id`, `work_start`, `allowed_late_minutes`, `block_after_minutes`, `token_lifetime_minutes`, `enable_penalties`, `penalty_per_minute`, `max_penalty_per_day`, `updated_at`, `start_time`, `max_checkin_minutes`, `allow_manual`) VALUES
(1, '09:00:00', 600, 600, 10, 0, 1.00, 50.00, '2025-12-19 11:40:48', '09:00:00', 120, 0);

-- --------------------------------------------------------

--
-- Структура таблицы `branches`
--

CREATE TABLE `branches` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `rate_per_shift` int(11) NOT NULL DEFAULT 0,
  `color` varchar(7) NOT NULL DEFAULT '#4CAF50',
  `max_workers_per_day` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `branches`
--

INSERT INTO `branches` (`id`, `title`, `address`, `phone`, `active`, `created_at`, `rate_per_shift`, `color`, `max_workers_per_day`) VALUES
(1, 'Kaufland Mircea', 'Bulevardul Mircea cel Bătrîn 25/2', '60979333', 1, '2025-12-18 00:12:11', 0, '#4CAF50', 1),
(2, 'Kaufland Mircea', 'Bulevardul Mircea cel Bătrîn 25/2', '60979333', 0, '2025-12-18 00:39:37', 0, '#4CAF50', 1),
(3, 'Kiev', 'Ghh', '', 1, '2025-12-19 10:44:05', 0, '#0000ff', 1);

-- --------------------------------------------------------

--
-- Структура таблицы `branch_qr_tokens`
--

CREATE TABLE `branch_qr_tokens` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `used` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `branch_tokens`
--

CREATE TABLE `branch_tokens` (
  `id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `token` varchar(64) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `delete_requests`
--

CREATE TABLE `delete_requests` (
  `id` int(11) NOT NULL,
  `entity_type` enum('branch','user') NOT NULL,
  `entity_id` int(11) NOT NULL,
  `requested_by` int(11) NOT NULL,
  `requested_at` datetime DEFAULT current_timestamp(),
  `status` enum('pending','approved','rejected','expired') NOT NULL DEFAULT 'pending',
  `decided_by` int(11) DEFAULT NULL,
  `decided_at` datetime DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `comment` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `delete_requests`
--

INSERT INTO `delete_requests` (`id`, `entity_type`, `entity_id`, `requested_by`, `requested_at`, `status`, `decided_by`, `decided_at`, `expires_at`, `comment`) VALUES
(1, 'branch', 2, 1, '2025-12-19 04:07:16', 'approved', 1, '2025-12-19 10:04:08', '2025-12-26 09:07:16', NULL),
(2, 'branch', 2, 1, '2025-12-19 10:04:26', 'approved', 1, '2025-12-19 10:04:37', '2025-12-26 15:04:26', NULL),
(3, 'branch', 2, 1, '2025-12-19 10:06:20', 'approved', 1, '2025-12-19 10:15:14', '2025-12-26 15:06:20', NULL);

-- --------------------------------------------------------

--
-- Структура таблицы `knowledge_views`
--

CREATE TABLE `knowledge_views` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `theme_id` int(11) DEFAULT NULL,
  `subtheme_id` int(11) DEFAULT NULL,
  `viewed_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `knowledge_views`
--

INSERT INTO `knowledge_views` (`id`, `user_id`, `theme_id`, `subtheme_id`, `viewed_at`) VALUES
(1, 3, 2, NULL, '2025-12-17 16:57:35'),
(2, 3, 2, NULL, '2025-12-17 17:01:35'),
(3, 3, 2, NULL, '2025-12-17 17:07:51'),
(4, 3, 2, NULL, '2025-12-17 17:16:31');

-- --------------------------------------------------------

--
-- Структура таблицы `late_penalties`
--

CREATE TABLE `late_penalties` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `work_date` date NOT NULL,
  `late_minutes` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `late_penalties`
--

INSERT INTO `late_penalties` (`id`, `user_id`, `work_date`, `late_minutes`, `amount`, `created_at`) VALUES
(1, 4, '2025-03-12', 17, 24.00, '2025-12-19 11:17:41');

-- --------------------------------------------------------

--
-- Структура таблицы `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `entity_type` varchar(30) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `body` text DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `permissions`
--

CREATE TABLE `permissions` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `title` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `permissions`
--

INSERT INTO `permissions` (`id`, `code`, `title`) VALUES
(1, 'ADMIN_MANAGE', 'Управлять администраторами'),
(2, 'USER_APPROVE', 'Подтверждать пользователей'),
(3, 'TEST_MANAGE', 'Создавать/редактировать тесты'),
(4, 'RESULT_VIEW', 'Смотреть результаты'),
(5, 'SETTINGS_MANAGE', 'Менять настройки системы'),
(6, 'BRANCH_MANAGE', 'Управление филиалами'),
(7, 'SCHEDULE_MANAGE', 'Управление графиком'),
(11, 'CHECKIN_VIEW', 'Просмотр отметок сотрудников'),
(12, 'CHECKIN_MANAGE', 'Управление отметками'),
(13, 'LATE_VIEW', 'Просмотр опозданий'),
(14, 'LATE_MANAGE', 'Управление опозданиями'),
(15, 'PENALTY_MANAGE', 'Управление штрафами'),
(16, 'CHECKIN_SELF', 'Отметка прихода');

-- --------------------------------------------------------

--
-- Структура таблицы `questions`
--

CREATE TABLE `questions` (
  `id` int(11) NOT NULL,
  `subtheme_id` int(11) DEFAULT NULL,
  `question` text NOT NULL,
  `a1` varchar(255) NOT NULL,
  `a2` varchar(255) NOT NULL,
  `a3` varchar(255) NOT NULL,
  `a4` varchar(255) NOT NULL,
  `correct` tinyint(4) NOT NULL,
  `hint_text` text DEFAULT NULL,
  `hint_link` varchar(255) DEFAULT NULL,
  `score` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `questions`
--

INSERT INTO `questions` (`id`, `subtheme_id`, `question`, `a1`, `a2`, `a3`, `a4`, `correct`, `hint_text`, `hint_link`, `score`) VALUES
(1, NULL, 'Привет', 'Привет', 'Пока', 'Не', 'надо', 1, 'Привет', NULL, 1),
(2, NULL, '3213', '213', '213', '213', '213', 1, NULL, NULL, 1),
(3, NULL, '3213', '213', '213', '213', '213', 1, NULL, NULL, 1);

-- --------------------------------------------------------

--
-- Структура таблицы `results`
--

CREATE TABLE `results` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `test_id` int(11) NOT NULL,
  `score` int(11) NOT NULL,
  `passed` tinyint(1) NOT NULL,
  `hints_used` int(11) DEFAULT 0,
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `subthemes`
--

CREATE TABLE `subthemes` (
  `id` int(11) NOT NULL,
  `theme_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `subthemes`
--

INSERT INTO `subthemes` (`id`, `theme_id`, `title`, `content`) VALUES
(1, 1, 'уцвкцук', NULL),
(2, 1, 'ап', NULL),
(3, 1, 'Ьчочоаоа', '<p>Оаоаоалалалал</p>');

-- --------------------------------------------------------

--
-- Структура таблицы `surveys`
--

CREATE TABLE `surveys` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `theme_id` int(11) DEFAULT NULL,
  `subtheme_id` int(11) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `surveys`
--

INSERT INTO `surveys` (`id`, `title`, `theme_id`, `subtheme_id`, `active`, `created_at`) VALUES
(1, 'Что делать при конфликте с клиентом?', NULL, NULL, 1, '2025-12-17 14:12:26'),
(2, 'парпапр', NULL, NULL, 1, '2025-12-17 14:23:43');

-- --------------------------------------------------------

--
-- Структура таблицы `survey_answers`
--

CREATE TABLE `survey_answers` (
  `id` int(11) NOT NULL,
  `survey_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `answer` text NOT NULL,
  `answered_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `survey_answers`
--

INSERT INTO `survey_answers` (`id`, `survey_id`, `question_id`, `user_id`, `answer`, `answered_at`) VALUES
(1, 1, 1, 3, 'ппп', '2025-12-17 14:16:07'),
(2, 2, 5, 3, 'рп', '2025-12-17 14:29:06');

-- --------------------------------------------------------

--
-- Структура таблицы `survey_questions`
--

CREATE TABLE `survey_questions` (
  `id` int(11) NOT NULL,
  `survey_id` int(11) NOT NULL,
  `question` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `survey_questions`
--

INSERT INTO `survey_questions` (`id`, `survey_id`, `question`) VALUES
(1, 1, 'пукпе'),
(2, 1, 'нпкен'),
(3, 1, 'екнкен'),
(4, 1, 'генге'),
(5, 2, 'еукеуке');

-- --------------------------------------------------------

--
-- Структура таблицы `tests`
--

CREATE TABLE `tests` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `type` enum('simple','themed') NOT NULL,
  `time_limit` int(11) DEFAULT NULL,
  `attempt_limit` int(11) DEFAULT NULL,
  `pass_score` int(11) DEFAULT 0,
  `access_password` varchar(50) NOT NULL,
  `allow_hints` tinyint(1) DEFAULT 1,
  `show_correct_on_error` tinyint(1) DEFAULT 0,
  `show_correct_on_finish` tinyint(1) DEFAULT 1,
  `active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `test_questions`
--

CREATE TABLE `test_questions` (
  `test_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Структура таблицы `themes`
--

CREATE TABLE `themes` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` longtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `themes`
--

INSERT INTO `themes` (`id`, `title`, `content`) VALUES
(1, 'аавав', NULL),
(2, 'Ппппп', '<p>Ьсочоаоалалалаоо</p>');

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) DEFAULT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `telegram_username` varchar(64) DEFAULT NULL,
  `fullname` varchar(100) DEFAULT NULL,
  `birthdate` date DEFAULT NULL,
  `gender` enum('male','female') DEFAULT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('owner','admin','employee') NOT NULL DEFAULT 'employee',
  `status` enum('pending','active','blocked') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `branch_id` int(11) DEFAULT NULL,
  `telegram` varchar(100) DEFAULT NULL,
  `viber` varchar(50) DEFAULT NULL,
  `whatsapp` varchar(50) DEFAULT NULL,
  `theme` enum('light','dark') NOT NULL DEFAULT 'light'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `username`, `first_name`, `last_name`, `phone`, `telegram_username`, `fullname`, `birthdate`, `gender`, `password_hash`, `role`, `status`, `created_at`, `branch_id`, `telegram`, `viber`, `whatsapp`, `theme`) VALUES
(1, 'Alexandr_Owner', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$RgCH9f.sXVewwJuBYnfr9e1HVre2zPa735RCHVO.3KxSSkCoxoKD.', 'owner', 'active', '2025-12-16 20:20:19', NULL, NULL, NULL, NULL, 'light'),
(2, 'Admin_Test', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '$2y$10$eBQVQVw7UdxbakerikYs6OKAeCwBmnJjw5ih8BMAIxA7BfC//KdM2', 'admin', 'active', '2025-12-17 16:50:16', NULL, NULL, NULL, NULL, 'light'),
(3, NULL, NULL, NULL, '79141790', NULL, 'Александр', '1990-02-26', NULL, '$2y$10$2aUW.V7q2kz1OUroTfMyWuyGQt/sDa4l2BG/hKTzupJDu6ks2mlpS', 'employee', 'active', '2025-12-17 17:04:18', NULL, NULL, NULL, NULL, 'light'),
(4, NULL, NULL, NULL, '079141790', NULL, 'Pcelintev Daniela', '1996-10-30', NULL, '$2y$10$AWDsKerXX4b.P69chc4WZuwQwdgMQGgZs1R0qik5eSXy4tBgKaXGK', 'employee', 'active', '2025-12-18 15:38:15', NULL, NULL, NULL, NULL, 'light'),
(5, NULL, NULL, NULL, '069253325', NULL, 'Pcelintev Vadim', '1978-02-25', NULL, '$2y$10$ITvdBTzzFYaAbqvq2EDjpOfg14d9M.G9kuocMwsDdnzb70L/zUAMO', 'employee', 'active', '2025-12-19 15:40:34', NULL, NULL, NULL, NULL, 'light'),
(6, NULL, NULL, NULL, '79222222', 'dhdsfj', 'Pcelintev Alexandr', '1990-12-12', NULL, '$2y$10$u4v6vs6R.5eHo0Un5T.YTOdO7DXftKcKgfFcsQij3ipXks/ce1fyy', 'employee', 'active', '2025-12-20 08:36:23', NULL, NULL, NULL, NULL, 'light'),
(7, NULL, NULL, NULL, '79141791', 'adasd', 'fsdfsddsa', NULL, 'male', '$2y$10$VwhlDDvPtfxkHDo4CVqjRO7I8GOf7HZENltAG2svksPI6motV9fy6', 'employee', 'pending', '2025-12-20 16:03:23', NULL, NULL, NULL, NULL, 'light');

-- --------------------------------------------------------

--
-- Структура таблицы `user_badges`
--

CREATE TABLE `user_badges` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `badge_code` varchar(50) NOT NULL,
  `awarded_at` datetime NOT NULL,
  `notified` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `user_badges`
--

INSERT INTO `user_badges` (`id`, `user_id`, `badge_code`, `awarded_at`, `notified`) VALUES
(1, 3, 'surveys', '2025-12-18 08:16:27', 0);

-- --------------------------------------------------------

--
-- Структура таблицы `user_permissions`
--

CREATE TABLE `user_permissions` (
  `user_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `user_permissions`
--

INSERT INTO `user_permissions` (`user_id`, `permission_id`) VALUES
(1, 1),
(2, 1),
(1, 2),
(2, 2),
(1, 3),
(2, 3),
(1, 4),
(2, 4),
(1, 5),
(2, 5),
(1, 7),
(1, 11),
(1, 13),
(1, 14),
(1, 15),
(1, 16);

-- --------------------------------------------------------

--
-- Структура таблицы `work_checkins`
--

CREATE TABLE `work_checkins` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `work_date` date NOT NULL,
  `checkin_time` datetime NOT NULL,
  `late_minutes` int(11) DEFAULT 0,
  `manual` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `work_checkins`
--

INSERT INTO `work_checkins` (`id`, `user_id`, `branch_id`, `work_date`, `checkin_time`, `late_minutes`, `manual`) VALUES
(1, 3, 1, '2025-12-19', '0000-00-00 00:00:00', 0, 0),
(2, 4, 2, '2025-03-12', '0000-00-00 00:00:00', 17, 0),
(6, 4, 1, '2025-12-19', '0000-00-00 00:00:00', 168, 0);

-- --------------------------------------------------------

--
-- Структура таблицы `work_schedule`
--

CREATE TABLE `work_schedule` (
  `id` int(11) NOT NULL,
  `work_date` date NOT NULL,
  `user_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `source` enum('manual','google') NOT NULL DEFAULT 'manual',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `work_schedule`
--

INSERT INTO `work_schedule` (`id`, `work_date`, `user_id`, `branch_id`, `source`, `created_by`, `created_at`) VALUES
(7, '2025-12-21', 4, 1, 'manual', NULL, '2025-12-18 11:55:16'),
(11, '2025-12-27', 4, 1, 'manual', NULL, '2025-12-18 12:37:31'),
(12, '2025-12-27', 3, 1, 'manual', NULL, '2025-12-18 12:37:33'),
(13, '2025-12-26', 3, 1, 'manual', NULL, '2025-12-18 12:37:35'),
(15, '2025-12-24', 3, 1, 'manual', NULL, '2025-12-18 12:37:39'),
(16, '2025-12-12', 4, 1, 'manual', NULL, '2025-12-18 14:39:21'),
(17, '2025-12-11', 4, 1, 'manual', NULL, '2025-12-18 14:39:24'),
(18, '2025-12-10', 4, 1, 'manual', NULL, '2025-12-18 14:39:30'),
(19, '2025-12-09', 4, 1, 'manual', NULL, '2025-12-18 14:39:33'),
(20, '2025-12-18', 4, 1, 'manual', NULL, '2025-12-18 14:50:23'),
(21, '2025-12-25', 4, 1, 'manual', NULL, '2025-12-18 14:50:26'),
(22, '2025-12-17', 4, 1, 'manual', NULL, '2025-12-18 14:50:33'),
(23, '2025-12-19', 4, 1, 'manual', NULL, '2025-12-19 06:37:43'),
(24, '2025-12-31', 4, 1, 'manual', NULL, '2025-12-19 10:38:03'),
(25, '2025-12-30', 4, 1, 'manual', NULL, '2025-12-19 10:38:07'),
(26, '2025-12-20', 5, 1, 'manual', NULL, '2025-12-19 10:43:22'),
(27, '2025-12-28', 4, 1, 'manual', NULL, '2025-12-19 10:43:24'),
(28, '2025-12-13', 4, 1, 'manual', NULL, '2025-12-19 10:43:27'),
(29, '2025-12-19', 5, 3, 'manual', NULL, '2025-12-19 10:44:20'),
(30, '2025-12-26', 5, 3, 'manual', NULL, '2025-12-19 10:44:23'),
(31, '2025-12-27', 5, 3, 'manual', NULL, '2025-12-19 10:44:29'),
(32, '2025-12-16', 5, 3, 'manual', NULL, '2025-12-19 10:44:36');

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `actions`
--
ALTER TABLE `actions`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `action_branches`
--
ALTER TABLE `action_branches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `action_id` (`action_id`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Индексы таблицы `action_user_status`
--
ALTER TABLE `action_user_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `action_id` (`action_id`,`user_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `attendance_logs`
--
ALTER TABLE `attendance_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user_day` (`user_id`,`work_date`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Индексы таблицы `attendance_penalties`
--
ALTER TABLE `attendance_penalties`
  ADD PRIMARY KEY (`id`),
  ADD KEY `attendance_id` (`attendance_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `attendance_settings`
--
ALTER TABLE `attendance_settings`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `branch_qr_tokens`
--
ALTER TABLE `branch_qr_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_token` (`token`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Индексы таблицы `branch_tokens`
--
ALTER TABLE `branch_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_branch` (`branch_id`);

--
-- Индексы таблицы `delete_requests`
--
ALTER TABLE `delete_requests`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `knowledge_views`
--
ALTER TABLE `knowledge_views`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_view` (`user_id`,`theme_id`,`subtheme_id`);

--
-- Индексы таблицы `late_penalties`
--
ALTER TABLE `late_penalties`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user_day` (`user_id`,`work_date`);

--
-- Индексы таблицы `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `is_read` (`is_read`),
  ADD KEY `entity_type` (`entity_type`,`entity_id`);

--
-- Индексы таблицы `permissions`
--
ALTER TABLE `permissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Индексы таблицы `questions`
--
ALTER TABLE `questions`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `results`
--
ALTER TABLE `results`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `test_id` (`test_id`);

--
-- Индексы таблицы `subthemes`
--
ALTER TABLE `subthemes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `theme_id` (`theme_id`);

--
-- Индексы таблицы `surveys`
--
ALTER TABLE `surveys`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `survey_answers`
--
ALTER TABLE `survey_answers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `question_id` (`question_id`,`user_id`),
  ADD KEY `survey_id` (`survey_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Индексы таблицы `survey_questions`
--
ALTER TABLE `survey_questions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `survey_id` (`survey_id`);

--
-- Индексы таблицы `tests`
--
ALTER TABLE `tests`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `test_questions`
--
ALTER TABLE `test_questions`
  ADD PRIMARY KEY (`test_id`,`question_id`),
  ADD KEY `question_id` (`question_id`);

--
-- Индексы таблицы `themes`
--
ALTER TABLE `themes`
  ADD PRIMARY KEY (`id`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `phone` (`phone`),
  ADD KEY `fk_users_branch` (`branch_id`);

--
-- Индексы таблицы `user_badges`
--
ALTER TABLE `user_badges`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user_badge` (`user_id`,`badge_code`),
  ADD KEY `idx_user` (`user_id`);

--
-- Индексы таблицы `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`user_id`,`permission_id`),
  ADD KEY `permission_id` (`permission_id`);

--
-- Индексы таблицы `work_checkins`
--
ALTER TABLE `work_checkins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user_day` (`user_id`,`work_date`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Индексы таблицы `work_schedule`
--
ALTER TABLE `work_schedule`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_user_date` (`user_id`,`work_date`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `actions`
--
ALTER TABLE `actions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `action_branches`
--
ALTER TABLE `action_branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `action_user_status`
--
ALTER TABLE `action_user_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `attendance_logs`
--
ALTER TABLE `attendance_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `attendance_penalties`
--
ALTER TABLE `attendance_penalties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `attendance_settings`
--
ALTER TABLE `attendance_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `branch_qr_tokens`
--
ALTER TABLE `branch_qr_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `branch_tokens`
--
ALTER TABLE `branch_tokens`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `delete_requests`
--
ALTER TABLE `delete_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `knowledge_views`
--
ALTER TABLE `knowledge_views`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT для таблицы `late_penalties`
--
ALTER TABLE `late_penalties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `permissions`
--
ALTER TABLE `permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT для таблицы `questions`
--
ALTER TABLE `questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `results`
--
ALTER TABLE `results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `subthemes`
--
ALTER TABLE `subthemes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT для таблицы `surveys`
--
ALTER TABLE `surveys`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `survey_answers`
--
ALTER TABLE `survey_answers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `survey_questions`
--
ALTER TABLE `survey_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT для таблицы `tests`
--
ALTER TABLE `tests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `themes`
--
ALTER TABLE `themes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT для таблицы `user_badges`
--
ALTER TABLE `user_badges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT для таблицы `work_checkins`
--
ALTER TABLE `work_checkins`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT для таблицы `work_schedule`
--
ALTER TABLE `work_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `action_branches`
--
ALTER TABLE `action_branches`
  ADD CONSTRAINT `action_branches_ibfk_1` FOREIGN KEY (`action_id`) REFERENCES `actions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `action_branches_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `action_user_status`
--
ALTER TABLE `action_user_status`
  ADD CONSTRAINT `action_user_status_ibfk_1` FOREIGN KEY (`action_id`) REFERENCES `actions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `action_user_status_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `attendance_logs`
--
ALTER TABLE `attendance_logs`
  ADD CONSTRAINT `attendance_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_logs_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `attendance_penalties`
--
ALTER TABLE `attendance_penalties`
  ADD CONSTRAINT `attendance_penalties_ibfk_1` FOREIGN KEY (`attendance_id`) REFERENCES `attendance_logs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `attendance_penalties_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `branch_qr_tokens`
--
ALTER TABLE `branch_qr_tokens`
  ADD CONSTRAINT `branch_qr_tokens_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`);

--
-- Ограничения внешнего ключа таблицы `branch_tokens`
--
ALTER TABLE `branch_tokens`
  ADD CONSTRAINT `branch_tokens_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `results`
--
ALTER TABLE `results`
  ADD CONSTRAINT `results_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `results_ibfk_2` FOREIGN KEY (`test_id`) REFERENCES `tests` (`id`);

--
-- Ограничения внешнего ключа таблицы `subthemes`
--
ALTER TABLE `subthemes`
  ADD CONSTRAINT `subthemes_ibfk_1` FOREIGN KEY (`theme_id`) REFERENCES `themes` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `survey_answers`
--
ALTER TABLE `survey_answers`
  ADD CONSTRAINT `survey_answers_ibfk_1` FOREIGN KEY (`survey_id`) REFERENCES `surveys` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `survey_answers_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `survey_questions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `survey_answers_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Ограничения внешнего ключа таблицы `survey_questions`
--
ALTER TABLE `survey_questions`
  ADD CONSTRAINT `survey_questions_ibfk_1` FOREIGN KEY (`survey_id`) REFERENCES `surveys` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `test_questions`
--
ALTER TABLE `test_questions`
  ADD CONSTRAINT `test_questions_ibfk_1` FOREIGN KEY (`test_id`) REFERENCES `tests` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `test_questions_ibfk_2` FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL;

--
-- Ограничения внешнего ключа таблицы `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `work_checkins`
--
ALTER TABLE `work_checkins`
  ADD CONSTRAINT `work_checkins_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `work_checkins_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

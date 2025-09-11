<?php

namespace WP_Easy\DocumentDownloader;

defined('ABSPATH') || exit;

final class Scheduled_Notifications
{
    public static function init(): void
    {
        add_action('init', [self::class, 'schedule_cron_jobs']);
        add_action('dd_daily_report', [self::class, 'send_daily_report']);
        add_action('dd_weekly_report', [self::class, 'send_weekly_report']);
        add_action('dd_monthly_report', [self::class, 'send_monthly_report']);
    }

    /**
     * Schedule cron jobs based on notification settings
     */
    public static function schedule_cron_jobs(): void
    {
        $opts = Settings::get_options();
        
        if (empty($opts['notify_schedule']) || empty($opts['notification_email'])) {
            // Unschedule all events if disabled
            wp_clear_scheduled_hook('dd_daily_report');
            wp_clear_scheduled_hook('dd_weekly_report');
            wp_clear_scheduled_hook('dd_monthly_report');
            return;
        }

        $schedule = $opts['notification_schedule'];
        
        // Clear all existing schedules first
        wp_clear_scheduled_hook('dd_daily_report');
        wp_clear_scheduled_hook('dd_weekly_report');
        wp_clear_scheduled_hook('dd_monthly_report');
        
        // Schedule the appropriate event
        $hook = "dd_{$schedule}_report";
        if (!wp_next_scheduled($hook)) {
            $time = self::get_next_schedule_time($schedule);
            wp_schedule_event($time, $schedule, $hook);
        }
    }

    /**
     * Get the next scheduled time based on frequency
     */
    private static function get_next_schedule_time(string $frequency): int
    {
        $now = current_time('timestamp');
        
        switch ($frequency) {
            case 'daily':
                // Schedule for 9 AM next day
                $next_time = strtotime('tomorrow 9:00 AM', $now);
                break;
            case 'weekly':
                // Schedule for next Monday 9 AM
                $next_time = strtotime('next monday 9:00 AM', $now);
                break;
            case 'monthly':
                // Schedule for 1st of next month 9 AM
                $next_time = strtotime('first day of next month 9:00 AM', $now);
                break;
            default:
                $next_time = $now + HOUR_IN_SECONDS;
        }
        
        return $next_time;
    }

    /**
     * Send daily report
     */
    public static function send_daily_report(): void
    {
        self::send_report('daily');
    }

    /**
     * Send weekly report
     */
    public static function send_weekly_report(): void
    {
        self::send_report('weekly');
    }

    /**
     * Send monthly report
     */
    public static function send_monthly_report(): void
    {
        self::send_report('monthly');
    }

    /**
     * Send the scheduled report email with CSV attachment
     */
    private static function send_report(string $frequency): void
    {
        $opts = Settings::get_options();
        
        if (empty($opts['notify_schedule']) || empty($opts['notification_email'])) {
            return;
        }

        $to = $opts['notification_email'];
        $period_data = self::get_report_period($frequency);
        $downloads = self::get_downloads_for_period($period_data['start'], $period_data['end']);
        
        if (empty($downloads)) {
            // No downloads to report
            return;
        }

        // Generate CSV file
        $csv_file = self::generate_csv_report($downloads, $frequency);
        
        if (!$csv_file) {
            return;
        }

        // Prepare email - use simple subject without placeholders
        $subject = "Document Download {$frequency} Report - " . wp_date('Y-m-d');
        $download_count = count($downloads);
        $period_text = ucfirst($frequency);
        
        $message = "
        <h2>Document Download {$period_text} Report</h2>
        <p><strong>Period:</strong> {$period_data['start_formatted']} to {$period_data['end_formatted']}</p>
        <p><strong>Total Downloads:</strong> {$download_count}</p>
        <p>Please find the detailed report attached as a CSV file.</p>
        <hr>
        <p><em>This is an automated report from your Document Downloader plugin.</em></p>
        ";

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
        ];

        // Send email with attachment
        $sent = wp_mail($to, $subject, $message, $headers, [$csv_file]);
        
        // Clean up temporary file
        if (file_exists($csv_file)) {
            unlink($csv_file);
        }
    }

    /**
     * Get report period dates
     */
    private static function get_report_period(string $frequency): array
    {
        $now = current_time('timestamp');
        
        switch ($frequency) {
            case 'daily':
                $start = strtotime('yesterday', $now);
                $end = strtotime('today', $now) - 1;
                break;
            case 'weekly':
                $start = strtotime('last monday', $now);
                $end = strtotime('last sunday 23:59:59', $now);
                break;
            case 'monthly':
                $start = strtotime('first day of last month', $now);
                $end = strtotime('last day of last month 23:59:59', $now);
                break;
            default:
                $start = $now - DAY_IN_SECONDS;
                $end = $now;
        }
        
        return [
            'start' => $start,
            'end' => $end,
            'start_formatted' => wp_date('Y-m-d', $start),
            'end_formatted' => wp_date('Y-m-d', $end),
        ];
    }

    /**
     * Get downloads for the specified period
     */
    private static function get_downloads_for_period(int $start_time, int $end_time): array
    {
        global $wpdb;
        
        $table = $wpdb->prefix . 'das_downloads';
        
        $sql = "SELECT * FROM {$table} WHERE downloaded_at >= %s AND downloaded_at <= %s ORDER BY downloaded_at DESC";
        
        return $wpdb->get_results($wpdb->prepare(
            $sql,
            wp_date('Y-m-d H:i:s', $start_time),
            wp_date('Y-m-d H:i:s', $end_time)
        ), ARRAY_A) ?: [];
    }

    /**
     * Generate CSV report file
     */
    private static function generate_csv_report(array $downloads, string $frequency): string
    {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/temp';
        
        if (!wp_mkdir_p($temp_dir)) {
            return '';
        }
        
        $filename = "download-report-{$frequency}-" . wp_date('Y-m-d-H-i-s') . '.csv';
        $filepath = $temp_dir . '/' . $filename;
        
        $file = fopen($filepath, 'w');
        if (!$file) {
            return '';
        }
        
        // CSV headers (same as Downloads admin page)
        $headers = [
            'Downloaded At',
            'File Name', 
            'Document Title',
            'User Name',
            'Email',
            'Phone',
            'IP Address',
            'User Agent'
        ];
        
        fputcsv($file, $headers);
        
        // Add data rows
        foreach ($downloads as $download) {
            $row = [
                $download['downloaded_at'],
                $download['file_name'] ?? '',
                $download['title'] ?? '',
                $download['name'] ?? '',
                $download['email'] ?? '',
                $download['phone'] ?? '',
                $download['ip_address'] ?? '',
                $download['user_agent'] ?? ''
            ];
            
            fputcsv($file, $row);
        }
        
        fclose($file);
        
        return file_exists($filepath) ? $filepath : '';
    }
}
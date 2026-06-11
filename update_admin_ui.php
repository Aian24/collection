<?php
$file = 'admin/admin.php';
if (!file_exists($file)) {
    die("File not found");
}

$content = file_get_contents($file);

$new_style = <<<'CSS'
<style>
        /* =========================================================
           MODERN UI/UX DASHBOARD THEME
           ========================================================= */
        
        :root {
            --primary-color: #4318FF;
            --secondary-color: #868CFF;
            --background-color: #F4F7FE;
            --card-bg: #FFFFFF;
            --text-main: #2B3674;
            --text-muted: #A3AED0;
            --shadow-soft: 0px 18px 40px rgba(112, 144, 176, 0.12);
            --shadow-hover: 0px 20px 50px rgba(112, 144, 176, 0.2);
            --radius-lg: 20px;
            --radius-md: 16px;
            --radius-sm: 10px;
        }

        body {
            background-color: var(--background-color) !important;
            color: var(--text-main);
            font-family: 'Inter', sans-serif;
        }

        /* Topbar & Sidebar */
        .bg-gradient-primary {
            background: linear-gradient(135deg, var(--secondary-color) 0%, var(--primary-color) 100%) !important;
            background-size: cover;
        }
        
        .sidebar {
            box-shadow: 4px 0 24px rgba(67, 24, 255, 0.1);
            border-right: none !important;
        }

        .topbar {
            background-color: rgba(255, 255, 255, 0.8) !important;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 24px rgba(112, 144, 176, 0.08) !important;
        }

        /* Modern Cards */
        .card {
            background: var(--card-bg);
            border: none;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-soft);
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            overflow: hidden;
            margin-bottom: 24px;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }

        .card-header {
            background: transparent !important;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 24px 24px 0 24px !important;
            padding-bottom: 16px !important;
        }

        .card-header h6 {
            color: var(--text-main) !important;
            font-size: 1.1rem;
            font-weight: 700;
            letter-spacing: -0.02em;
        }

        /* Summary Cards Enhancements */
        .summary-card {
            position: relative;
            background: var(--card-bg);
            border-radius: var(--radius-lg);
            padding: 24px;
            box-shadow: var(--shadow-soft);
            transition: all 0.3s ease;
            overflow: hidden;
            z-index: 1;
        }

        .summary-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 4px;
            background: linear-gradient(90deg, var(--primary-color), #00F2FE);
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .summary-card:hover::before {
            opacity: 1;
        }

        .summary-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .summary-icon .icon-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            background: rgba(67, 24, 255, 0.05);
            color: var(--primary-color);
            transition: all 0.3s ease;
        }

        .summary-card:hover .icon-circle {
            transform: scale(1.1) rotate(10deg);
            background: var(--primary-color);
            color: #fff;
            box-shadow: 0 10px 20px rgba(67, 24, 255, 0.2);
        }

        .summary-details .text-uppercase {
            font-size: 0.85rem;
            font-weight: 600;
            color: var(--text-muted);
            letter-spacing: 0.5px;
        }

        .summary-details .amount {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-main);
            margin-top: 8px;
            line-height: 1.2;
        }

        /* Modern Tables */
        .table {
            color: var(--text-main);
            margin-bottom: 0;
        }

        .table thead th {
            border-bottom: none;
            border-top: none;
            text-transform: uppercase;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--text-muted);
            letter-spacing: 0.5px;
            padding: 16px 24px;
            background: transparent;
        }

        .table tbody td {
            vertical-align: middle;
            border-top: 1px solid rgba(0,0,0,0.03);
            padding: 16px 24px;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .table tbody tr {
            transition: background 0.2s ease;
        }

        .table tbody tr:hover {
            background: rgba(67, 24, 255, 0.02);
        }

        /* Buttons & Inputs */
        .btn-primary {
            background: var(--primary-color);
            border: none;
            border-radius: var(--radius-md);
            padding: 12px 24px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 14px rgba(67, 24, 255, 0.3);
        }

        .btn-primary:hover {
            background: #3311CC;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(67, 24, 255, 0.4);
        }

        .form-control {
            background: #F4F7FE;
            border: 1px solid transparent;
            border-radius: var(--radius-md);
            padding: 12px 20px;
            color: var(--text-main);
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            background: #FFFFFF;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(67, 24, 255, 0.1);
        }

        /* Modern Modal */
        .modal-content {
            border: none;
            border-radius: var(--radius-lg);
            box-shadow: 0 24px 50px rgba(0,0,0,0.2);
            overflow: hidden;
        }

        .modal-header {
            background: #fff;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            padding: 24px;
        }

        .modal-title {
            color: var(--text-main);
            font-weight: 700;
            font-size: 1.25rem;
        }
        
        .modal-header .close {
            color: var(--text-muted);
            opacity: 1;
            transition: color 0.2s;
        }

        .modal-header .close:hover {
            color: var(--primary-color);
        }

        .modal-body {
            padding: 24px;
        }

        /* Modern Dashboard Header */
        .dashboard-header {
            margin-bottom: 32px;
            padding-top: 16px;
        }

        .dashboard-title {
            font-size: 2.2rem;
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.03em;
            display: flex;
            align-items: center;
            gap: 16px;
            margin: 0;
        }

        .title-icon {
            color: var(--primary-color);
            font-size: 2.5rem;
            filter: drop-shadow(0 4px 8px rgba(67, 24, 255, 0.2));
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }

        .dashboard-subtitle {
            color: var(--text-muted);
            font-size: 1rem;
            font-weight: 500;
            margin-top: 8px;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--text-muted);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-color);
        }

        /* Loader Overlay Blur */
        .blur-when-loading {
            filter: blur(8px) grayscale(0.1);
            pointer-events: none;
            user-select: none;
            transition: filter 0.4s ease;
        }
        
        /* SLEEK MODERN SPINNER LOADER */
        #loader-overlay {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            z-index: 99999;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            transition: opacity 0.4s ease, visibility 0.4s;
        }

        .modern-spinner {
            width: 80px;
            height: 80px;
            position: relative;
        }

        .modern-spinner div {
            box-sizing: border-box;
            display: block;
            position: absolute;
            width: 64px;
            height: 64px;
            margin: 8px;
            border: 6px solid var(--primary-color);
            border-radius: 50%;
            animation: modern-spinner 1.2s cubic-bezier(0.5, 0, 0.5, 1) infinite;
            border-color: var(--primary-color) transparent transparent transparent;
        }

        .modern-spinner div:nth-child(1) { animation-delay: -0.45s; }
        .modern-spinner div:nth-child(2) { animation-delay: -0.3s; }
        .modern-spinner div:nth-child(3) { animation-delay: -0.15s; }

        @keyframes modern-spinner {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .loader-text {
            margin-top: 24px;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--text-main);
            letter-spacing: 0.02em;
            animation: pulse-text 1.5s ease-in-out infinite;
        }

        @keyframes pulse-text {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
CSS;

$new_loader = <<<'HTML'
    <!-- Modern Page Loader -->
    <div id="loader-overlay">
        <div class="modern-spinner">
            <div></div><div></div><div></div><div></div>
        </div>
        <div class="loader-text">Loading Dashboard...</div>
    </div>
HTML;

// 1. Replace entire <style> block
$content = preg_replace('/<style>.*?<\/style>/s', $new_style, $content, 1);

// 2. Replace loader
$loader_pattern = '/<!-- Page Loader -->.*?<div id="wrapper" class="blur-when-loading">/s';
$loader_replacement = $new_loader . "\n\n    <div id=\"wrapper\" class=\"blur-when-loading\">";

$content = preg_replace($loader_pattern, $loader_replacement, $content, 1);

file_put_contents($file, $content);
echo "Successfully updated admin UI and loader in admin.php\n";
?>

<?php
/**
 * Main sidebar.
 *
 * The menu is not written here — it comes from App\Support\Navigation, the same definition the top
 * navbar uses, so the two surfaces cannot disagree about what exists, what it is called, where it
 * goes or who may open it. This file only decides how a group looks (collapsible when it has more
 * than one page, a plain link when it is a page of its own).
 *
 * Rendering seams: pass $nav to render the menu for somebody else. The verify:nav harness uses that
 * to render this sidebar for a synthetic operator and URL without needing a session.
 */

$nav        = $nav ?? \App\Support\Navigation::forCurrentUser();
$first_name = $first_name ?? (auth()->user()->first_name ?? 'Not Login');
?>
<!-- Main Sidebar Container -->
<aside class="main-sidebar sidebar-light-primary elevation-4">
    <!-- Brand Logo -->
    <a href="<?= base_url('') ?>" class="brand-link">
        <img src="<?= base_url('assets/images/') ?>pfsense_rb.png" alt="logo" class="brand-image elevation-2"
            style="opacity: .8">
        <span class="brand-text font-weight-bold text-nowrap" style="letter-spacing: -0.5px;"><?= esc(strtoupper($app_name ?? 'PF-CENTRAL')) ?></span>
    </a>

    <!-- Sidebar -->
    <div class="sidebar">
        <!-- Sidebar user (optional) -->
        <div class="user-panel mt-3 pb-3 mb-3 d-flex">
            <div class="image">
                <img src="<?= base_url('assets/images/') ?>usericon.jpg" class="img-circle elevation-2"
                    alt="User Image">
            </div>
            <div class="info">
                <a href="#" class="d-block">Hi, <?= esc($first_name) ?></a>
            </div>
        </div>

        <!-- SidebarSearch Form -->
        <div class="form-inline">
            <div class="input-group" data-widget="sidebar-search">
                <input class="form-control form-control-sidebar" type="search" placeholder="Search" aria-label="Search">
                <div class="input-group-append">
                    <button class="btn btn-sidebar">
                        <i class="fas fa-search fa-fw"></i>
                    </button>
                </div>
            </div>
        </div>

        <nav class="mt-2">
            <!--
                Accordion, not a set of independently collapsible menus: opening a group closes whichever
                group was open before, so the sidebar never grows into a wall of open submenus and the
                operator's place in the menu stays where they just looked. AdminLTE's treeview widget
                owns this (it reads data-accordion), so the behaviour is not re-implemented here.
            -->
            <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="true">
                <?php foreach ($nav->sections() as $section): ?>
                    <?php if (empty($section['items'])): ?>
                        <!-- A page, not a group: one link, no submenu -->
                        <?php $active = $nav->isActive($section); ?>
                        <li class="nav-item">
                            <a href="<?= $nav->href($section) ?>"<?= $nav->target($section) ?>
                                class="nav-link <?= $active ? 'active' : '' ?><?= $nav->badgeHook($section) ?>">
                                <i class="nav-icon <?= esc($section['icon']) ?>"></i>
                                <p><?= esc(strtoupper($section['label'])) ?></p>
                            </a>
                        </li>
                    <?php else: ?>
                        <?php $groupActive = $nav->isGroupActive($section); ?>
                        <li class="nav-item has-treeview <?= $groupActive ? 'menu-open' : '' ?>">
                            <a href="#" class="nav-link <?= $groupActive ? 'active' : '' ?>">
                                <i class="nav-icon <?= esc($section['icon']) ?>"></i>
                                <p>
                                    <?= esc(strtoupper($section['group'])) ?>
                                    <i class="right fas fa-angle-left"></i>
                                </p>
                            </a>
                            <ul class="nav nav-treeview" style="display: <?= $groupActive ? 'block' : 'none' ?>;">
                                <?php foreach ($section['items'] as $item): ?>
                                    <?php $active = $nav->isActive($item); ?>
                                    <li class="nav-item">
                                        <a href="<?= $nav->href($item) ?>"<?= $nav->target($item) ?>
                                            class="nav-link <?= $active ? 'active' : '' ?><?= $nav->badgeHook($item) ?>">
                                            <i class="<?= esc($item['icon']) ?> nav-icon mr-2" style="margin-left: 1rem;"></i>
                                            <p><?= esc($item['label']) ?></p>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </nav>
        <!-- /.sidebar-menu -->
    </div>
    <!-- /.sidebar -->
</aside>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">

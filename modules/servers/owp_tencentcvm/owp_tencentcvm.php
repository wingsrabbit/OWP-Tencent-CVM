<?php

declare(strict_types=1);

use OwpTencentCvm\Config;
use OwpTencentCvm\Operations;

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/lib/bootstrap.php';

function owp_tencentcvm_MetaData(): array
{
    return [
        'DisplayName' => Config::DISPLAY_NAME,
        'APIVersion' => '1.1',
        'RequiresServer' => false,
    ];
}

function owp_tencentcvm_ConfigOptions(): array
{
    return Config::serverModuleOptions();
}

function owp_tencentcvm_CreateAccount(array $params): string
{
    return Operations::createAccount($params);
}

function owp_tencentcvm_SuspendAccount(array $params): string
{
    return Operations::suspendAccount($params);
}

function owp_tencentcvm_UnsuspendAccount(array $params): string
{
    return Operations::unsuspendAccount($params);
}

function owp_tencentcvm_TerminateAccount(array $params): string
{
    return Operations::terminateAccount($params);
}

function owp_tencentcvm_ChangePassword(array $params): string
{
    return Operations::changePassword($params);
}

function owp_tencentcvm_ChangePackage(array $params): string
{
    return Operations::changePackage($params);
}

function owp_tencentcvm_ClientArea(array $params): array
{
    return Operations::clientArea($params);
}

function owp_tencentcvm_ClientAreaCustomButtonArray(): array
{
    return [
        'Start Instance' => 'StartInstance',
        'Stop Instance' => 'StopInstance',
        'Reboot Instance' => 'RebootInstance',
        'Reset Password' => 'ResetInstancePassword',
        'Open Console' => 'OpenConsole',
    ];
}

function owp_tencentcvm_AdminCustomButtonArray(): array
{
    return [
        'Sync Instance Status' => 'SyncInstanceStatus',
    ];
}

function owp_tencentcvm_StartInstance(array $params): string
{
    return Operations::notImplemented('StartInstance');
}

function owp_tencentcvm_StopInstance(array $params): string
{
    return Operations::notImplemented('StopInstance');
}

function owp_tencentcvm_RebootInstance(array $params): string
{
    return Operations::notImplemented('RebootInstance');
}

function owp_tencentcvm_ResetInstancePassword(array $params): string
{
    return Operations::notImplemented('ResetInstancePassword');
}

function owp_tencentcvm_OpenConsole(array $params): string
{
    return Operations::notImplemented('OpenConsole');
}

function owp_tencentcvm_SyncInstanceStatus(array $params): string
{
    return Operations::syncInstanceStatus($params);
}

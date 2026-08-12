<?php

namespace App\Communication\Application\Port;

use App\Communication\Contract\SmsClientInterface;

/**
 * Application-layer bridge over the existing infrastructure SMS client contract.
 *
 * Legacy code continues to depend on {@see SmsClientInterface}.
 * New Application handlers should depend on this port; Infrastructure adapters
 * may implement both or resolve the active client via SmsClientResolver.
 */
interface SmsClientPort extends SmsClientInterface
{
}

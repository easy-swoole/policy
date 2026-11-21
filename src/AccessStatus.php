<?php

namespace EasySwoole\Policy;

enum AccessStatus: string
{
    case ALLOW = 'allow';
    case DENY = 'deny';
    case UNKNOWN = 'unknown';
}

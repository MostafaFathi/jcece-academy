<?php

namespace App;

enum AccessGrantSource: string
{
    case DirectPurchase = 'direct_purchase';
    case PackagePurchase = 'package_purchase';
    case Admin = 'admin';
    case Free = 'free';
    case Promotion = 'promotion';
}

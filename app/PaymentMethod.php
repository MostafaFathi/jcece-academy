<?php

namespace App;

enum PaymentMethod: string
{
    case BankTransfer = 'bank_transfer';
    case Wallet = 'wallet';
    case Manual = 'manual';
}

<?php

namespace App\Enums;

/**
 * Stable chart-of-accounts codes. Services resolve accounts by code, never by id,
 * so seeded ledgers stay portable across environments.
 */
enum AccountCode: string
{
    case CashInHand = '1010';
    case Bank = '1020';
    case ServiceChargeReceivable = '1030';

    case AccountsPayable = '2010';
    case AdvanceFromOwners = '2020';
    case SecurityDeposits = '2030';

    case GeneralFund = '3010';
    case SinkingFund = '3020';

    case ServiceChargeIncome = '4010';
    case LateFeeIncome = '4020';
    case BankInterest = '4030';
    case OtherIncome = '4040';

    case GuardSalary = '5010';
    case CommonElectricity = '5020';
    case LiftMaintenance = '5030';
    case Cleaning = '5040';
    case WaterWasa = '5050';
    case GeneratorFuel = '5060';
    case RepairsMaintenance = '5070';
    case Admin = '5080';
}

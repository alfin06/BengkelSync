<?php

namespace App\Enums;

/**
 * The single source of truth for permission names (SPEC section 2).
 *
 * Seeders, roles, policies and the frontend all read from this enum, so a
 * permission can only be renamed in one place. Format: `resource.action`.
 */
enum Permission: string
{
    case CustomerView = 'customer.view';
    case CustomerCreate = 'customer.create';
    case CustomerUpdate = 'customer.update';
    case CustomerDelete = 'customer.delete';

    case VehicleView = 'vehicle.view';
    case VehicleCreate = 'vehicle.create';
    case VehicleUpdate = 'vehicle.update';
    case VehicleDelete = 'vehicle.delete';

    case MechanicView = 'mechanic.view';
    case MechanicCreate = 'mechanic.create';
    case MechanicUpdate = 'mechanic.update';
    case MechanicDelete = 'mechanic.delete';
    case MechanicViewIdentity = 'mechanic.view_identity';

    case WorkOrderView = 'work_order.view';
    case WorkOrderCreate = 'work_order.create';
    case WorkOrderUpdate = 'work_order.update';
    case WorkOrderDelete = 'work_order.delete';
    case WorkOrderAssign = 'work_order.assign';
    case WorkOrderChangeStatus = 'work_order.change_status';
    case WorkOrderUpdateClosed = 'work_order.update_closed';

    case InvoiceView = 'invoice.view';
    case InvoiceCreate = 'invoice.create';
    case InvoiceUpdate = 'invoice.update';
    case InvoiceUpdatePaid = 'invoice.update_paid';
    case InvoiceVoid = 'invoice.void';
    case InvoiceDiscount = 'invoice.discount';
    case InvoiceToggleTax = 'invoice.toggle_tax';

    case PaymentView = 'payment.view';
    case PaymentCreate = 'payment.create';
    case PaymentUpdate = 'payment.update';
    case PaymentDelete = 'payment.delete';

    case PartView = 'part.view';
    case PartCreate = 'part.create';
    case PartUpdate = 'part.update';
    case PartDelete = 'part.delete';

    case StockView = 'stock.view';
    case StockAdjust = 'stock.adjust';
    case StockTransfer = 'stock.transfer';

    case SupplierView = 'supplier.view';
    case SupplierCreate = 'supplier.create';
    case SupplierUpdate = 'supplier.update';
    case SupplierDelete = 'supplier.delete';

    case PurchaseView = 'purchase.view';
    case PurchaseCreate = 'purchase.create';
    case PurchaseUpdate = 'purchase.update';
    case PurchaseDelete = 'purchase.delete';

    case SupplierPaymentView = 'supplier_payment.view';
    case SupplierPaymentCreate = 'supplier_payment.create';
    case SupplierPaymentUpdate = 'supplier_payment.update';
    case SupplierPaymentDelete = 'supplier_payment.delete';

    case CashView = 'cash.view';
    case CashTransfer = 'cash.transfer';
    case CashAdjust = 'cash.adjust';

    case ExpenseView = 'expense.view';
    case ExpenseCreate = 'expense.create';
    case ExpenseUpdate = 'expense.update';
    case ExpenseDelete = 'expense.delete';

    case DashboardView = 'dashboard.view';
    case DashboardViewFinancials = 'dashboard.view_financials';

    case ReportView = 'report.view';

    case BranchView = 'branch.view';
    case BranchCreate = 'branch.create';
    case BranchUpdate = 'branch.update';
    case BranchDelete = 'branch.delete';

    case UserView = 'user.view';
    case UserCreate = 'user.create';
    case UserUpdate = 'user.update';
    case UserDelete = 'user.delete';

    case RoleView = 'role.view';
    case RoleCreate = 'role.create';
    case RoleUpdate = 'role.update';
    case RoleDelete = 'role.delete';

    case SettingsView = 'settings.view';
    case SettingsUpdate = 'settings.update';

    case AuditLogView = 'audit_log.view';

    case BillingManage = 'billing.manage';

    public function resource(): string
    {
        return explode('.', $this->value, 2)[0];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $permission): string => $permission->value, self::cases());
    }

    /**
     * @param  list<self>  $excluded
     * @return list<self>
     */
    public static function allExcept(array $excluded): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $permission): bool => ! in_array($permission, $excluded, true),
        ));
    }
}

<?php

namespace App\Enums;

/**
 * Default roles copied into every new tenant (SPEC section 2).
 *
 * Tenants may edit these roles or add their own later; only Owner is a
 * protected system role.
 */
enum RoleName: string
{
    case Owner = 'owner';
    case BranchManager = 'branch_manager';
    case FrontDesk = 'front_desk';
    case Finance = 'finance';
    case Warehouse = 'warehouse';

    public function label(): string
    {
        return __('roles.'.$this->value);
    }

    public function isSystem(): bool
    {
        return $this === self::Owner;
    }

    /**
     * @return list<Permission>
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::Owner => Permission::cases(),
            self::BranchManager => self::branchManager(),
            self::FrontDesk => self::frontDesk(),
            self::Finance => self::finance(),
            self::Warehouse => self::warehouse(),
        };
    }

    /**
     * @return list<Permission>
     */
    private static function branchManager(): array
    {
        return Permission::allExcept([
            Permission::BillingManage,
            Permission::RoleView,
            Permission::RoleCreate,
            Permission::RoleUpdate,
            Permission::RoleDelete,
            Permission::SettingsView,
            Permission::SettingsUpdate,
            Permission::BranchCreate,
            Permission::BranchDelete,
        ]);
    }

    /**
     * @return list<Permission>
     */
    private static function frontDesk(): array
    {
        return [
            Permission::CustomerView,
            Permission::CustomerCreate,
            Permission::CustomerUpdate,
            Permission::VehicleView,
            Permission::VehicleCreate,
            Permission::VehicleUpdate,
            Permission::MechanicView,
            Permission::ServiceView,
            Permission::WorkOrderView,
            Permission::WorkOrderCreate,
            Permission::WorkOrderUpdate,
            Permission::WorkOrderAssign,
            Permission::WorkOrderChangeStatus,
            Permission::InvoiceView,
            Permission::InvoiceCreate,
            // Pilot decision: Front Desk may give discounts for now; to be made stricter later.
            Permission::InvoiceDiscount,
            // Pilot decision: Front Desk may correct unpaid invoices; paid ones stay with Finance.
            Permission::InvoiceUpdate,
            Permission::PaymentView,
            Permission::PaymentCreate,
            Permission::PartView,
            Permission::PartCreate,
            Permission::StockView,
            Permission::PurchaseView,
            Permission::PurchaseCreate,
            Permission::SupplierPaymentOnReceipt,
            Permission::SupplierView,
            Permission::SupplierCreate,
            Permission::CashView,
            Permission::DashboardView,
        ];
    }

    /**
     * @return list<Permission>
     */
    private static function finance(): array
    {
        return [
            ...self::frontDesk(),
            Permission::MechanicViewIdentity,
            Permission::MechanicCreate,
            Permission::MechanicUpdate,
            Permission::ServiceCreate,
            Permission::ServiceUpdate,
            Permission::InvoiceUpdatePaid,
            Permission::InvoiceVoid,
            Permission::InvoiceToggleTax,
            Permission::PaymentUpdate,
            Permission::PaymentDelete,
            Permission::PartUpdate,
            Permission::StockAdjust,
            Permission::SupplierUpdate,
            Permission::PurchaseUpdate,
            Permission::SupplierPaymentView,
            Permission::SupplierPaymentCreate,
            Permission::SupplierPaymentUpdate,
            Permission::SupplierPaymentDelete,
            Permission::CashTransfer,
            Permission::CashAdjust,
            Permission::ExpenseView,
            Permission::ExpenseCreate,
            Permission::ExpenseUpdate,
            Permission::ExpenseDelete,
            Permission::DashboardViewFinancials,
            Permission::ReportView,
        ];
    }

    /**
     * @return list<Permission>
     */
    private static function warehouse(): array
    {
        return [
            Permission::PartView,
            Permission::PartCreate,
            Permission::PartUpdate,
            Permission::PartDelete,
            Permission::StockView,
            Permission::StockAdjust,
            Permission::StockTransfer,
            Permission::SupplierView,
            Permission::SupplierCreate,
            Permission::SupplierUpdate,
            Permission::SupplierDelete,
            Permission::PurchaseView,
            Permission::PurchaseCreate,
            Permission::PurchaseUpdate,
            Permission::DashboardView,
        ];
    }
}

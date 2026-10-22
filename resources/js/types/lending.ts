import type { Role } from './auth';

export type InterestMethod = 'flat' | 'declining_balance';

export type RepaymentFrequency = 'monthly' | 'weekly' | 'biweekly';

export type KycStatus = 'pending' | 'verified' | 'rejected';

export type SelectOption<TValue extends string | number = string> = {
    value: TValue;
    label: string;
};

export type InterestMethodOption = SelectOption<InterestMethod>;

export type RepaymentFrequencyOption = SelectOption<RepaymentFrequency>;

export type KycStatusOption = SelectOption<KycStatus>;

export type UserOption = SelectOption<number>;

export type Borrower = {
    id: number;
    user_id: number | null;
    name: string;
    national_id: string | null;
    phone: string;
    email: string | null;
    date_of_birth: string | null;
    address: string | null;
    kyc_status: KycStatus;
    kyc_status_label: string;
    notes: string | null;
    notify_due_soon: boolean;
    notify_overdue: boolean;
    notify_receipts: boolean;
    notify_statements: boolean;
    notify_sms: boolean;
    created_at: string | null;
};

export type BorrowerListItem = Borrower & {
    can: {
        view: boolean;
        update: boolean;
        delete: boolean;
    };
};

export type BorrowerImportError = {
    line: number;
    message: string;
};

export type BorrowerImportSummary = {
    imported: number;
    skipped: number;
    total_rows: number;
    errors: BorrowerImportError[];
    error_count: number;
};

export type BorrowerDetail = Borrower & {
    user: {
        id: number;
        name: string;
        email: string;
    } | null;
};

export type BorrowerLoanSummary = {
    id: number;
    product: string | null;
    principal: number;
    status: LoanStatus;
    status_label: string;
    is_open: boolean;
    disbursed_at: string | null;
    paid: number;
    outstanding: number;
    outstanding_principal: number;
    outstanding_interest: number;
    outstanding_fee: number;
    outstanding_penalty: number;
    arrears: number;
    days_past_due: number;
};

export type BorrowerPayment = {
    id: number;
    loan_id: number;
    borrower: string | null;
    amount: number;
    method: PaymentMethod;
    method_label: string;
    reference: string | null;
    payment_date: string;
    recorded_by: string | null;
};

export type BorrowerActivity = {
    id: number;
    event: string;
    event_label: string;
    description: string;
    user_name: string | null;
    created_at: string | null;
};

export type LoanProduct = {
    id: number;
    name: string;
    description: string | null;
    interest_method: InterestMethod;
    interest_method_label: string;
    interest_rate: string;
    repayment_frequency: RepaymentFrequency;
    repayment_frequency_label: string;
    min_amount: string;
    max_amount: string;
    min_term_months: number;
    max_term_months: number;
    processing_fee_rate: string;
    penalty_rate: string;
    grace_period_days: number;
    is_active: boolean;
    created_at: string | null;
};

export type LoanProductListItem = LoanProduct & {
    loans_count: number;
    can: {
        update: boolean;
        delete: boolean;
    };
};

export type LoanStatus =
    | 'draft'
    | 'pending'
    | 'approved'
    | 'rejected'
    | 'disbursed'
    | 'active'
    | 'closed'
    | 'defaulted'
    | 'written_off';

export type LoanStatusOption = SelectOption<LoanStatus>;

export type DisbursementMethod =
    | 'bank_transfer'
    | 'cash'
    | 'cheque'
    | 'mobile_money'
    | 'other';

export type DisbursementMethodOption = SelectOption<DisbursementMethod>;

export type PaymentMethod =
    | 'bank_transfer'
    | 'cash'
    | 'cheque'
    | 'mobile_money'
    | 'other';

export type PaymentMethodOption = SelectOption<PaymentMethod>;

export type LoanEventType =
    | 'created'
    | 'approved'
    | 'rejected'
    | 'disbursed'
    | 'activated'
    | 'closed'
    | 'defaulted'
    | 'written_off'
    | 'payment_recorded'
    | 'payment_reversed'
    | 'penalty_accrued'
    | 'restructured';

export type BorrowerOption = SelectOption<number>;

export type LoanProductOption = SelectOption<number> & {
    interest_rate: string;
    interest_method: InterestMethod;
    interest_method_label: string;
    repayment_frequency_label: string;
    min_amount: string;
    max_amount: string;
    min_term_months: number;
    max_term_months: number;
};

export type LoanSchedulePreview = {
    installments: {
        sequence: number;
        due_date: string;
        principal_due: number;
        interest_due: number;
        fee_due: number;
        balance: number;
        total: number;
    }[];
    totals: {
        principal: number;
        interest: number;
        fee: number;
        total: number;
    };
    periods: number;
};

export type Loan = {
    id: number;
    borrower_id: number;
    borrower_name: string;
    loan_product_id: number;
    loan_product_name: string;
    principal: string;
    interest_rate: string;
    interest_method: InterestMethod;
    interest_method_label: string;
    repayment_frequency: RepaymentFrequency;
    repayment_frequency_label: string;
    term_months: number;
    processing_fee_rate: string;
    penalty_rate: string;
    grace_period_days: number;
    days_past_due: number;
    status: LoanStatus;
    status_label: string;
    applied_at: string | null;
    approved_at: string | null;
    rejected_at: string | null;
    disbursed_at: string | null;
    closed_at: string | null;
    notes: string | null;
    created_at: string | null;
};

export type LoanListItem = Loan & {
    arrears: string;
    can: {
        view: boolean;
        statement: boolean;
        download_statement: boolean;
        approve: boolean;
        delete: boolean;
    };
};

export type LoanAbilities = {
    approve: boolean;
    reject: boolean;
    disburse: boolean;
    pay: boolean;
    default: boolean;
    write_off: boolean;
    restructure: boolean;
    statement: boolean;
    download_statement: boolean;
    delete: boolean;
};

export type LoanInstallment = {
    sequence: number;
    due_date: string;
    principal_due: string;
    interest_due: string;
    fee_due: string;
    balance: string;
    penalty_accrued: string;
    paid: string;
    outstanding: string;
    principal_outstanding: string;
    interest_outstanding: string;
    fee_outstanding: string;
    penalty_outstanding: string;
    overdue: boolean;
    days_past_due: number;
};

export type Disbursement = {
    id: number;
    amount: string;
    method: DisbursementMethod;
    method_label: string;
    reference: string | null;
    disbursed_at: string;
    recorded_by: string | null;
};

export type PaymentAllocation = {
    installment_sequence: number | null;
    principal_allocated: string;
    interest_allocated: string;
    fee_allocated: string;
    penalty_allocated: string;
};

export type Payment = {
    id: number;
    amount: string;
    method: PaymentMethod;
    method_label: string;
    reference: string | null;
    notes: string | null;
    payment_date: string;
    recorded_by: string | null;
    reversed_at: string | null;
    reversal_reason: string | null;
    reversed_by: string | null;
    can_reverse: boolean;
    allocations: PaymentAllocation[];
};

export type PaymentAging = {
    current: string;
    days_1_30: string;
    days_31_60: string;
    days_61_90: string;
    days_over_90: string;
};

export type PaymentBreakdown = {
    penalty: string;
    fee: string;
    interest: string;
    principal: string;
};

export type PaymentTotals = {
    payable: string;
    paid: string;
    outstanding: string;
    arrears: string;
    penalty: string;
    allocation: PaymentBreakdown;
    aging: PaymentAging;
};

export type PaymentFilters = {
    method: PaymentMethod | null;
    from: string | null;
    to: string | null;
};

export type PaymentReceipt = {
    id: number;
    number: string;
    payment_date: string;
    amount: string;
    method_label: string;
    reference: string | null;
    notes: string | null;
    recorded_by: string | null;
    reversed_at: string | null;
    reversal_reason: string | null;
    reversed_by: string | null;
    borrower_name: string;
    borrower_email: string | null;
    loan_id: number;
    product_name: string;
    allocations: PaymentAllocation[];
};

export type StatementPreview = {
    eligible: boolean;
    to: string | null;
    subject: string | null;
    html: string | null;
    message?: string;
};

export type RoleMatrixAbility = {
    label: string;
    description: string;
    roles: Record<Role, boolean>;
};

export type RoleMatrixGroup = {
    title: string;
    abilities: RoleMatrixAbility[];
};

export type LoanEvent = {
    id: number;
    action: LoanEventType;
    action_label: string;
    from_status: LoanStatus | null;
    from_status_label: string | null;
    to_status: LoanStatus | null;
    to_status_label: string | null;
    user: string | null;
    created_at: string | null;
};

export type NotificationStatus = 'queued' | 'sent' | 'failed' | 'skipped';

export type NotificationLogEntry = {
    id: number;
    type: string;
    type_label: string;
    channel: string;
    channel_label: string;
    status: NotificationStatus;
    status_label: string;
    error: string | null;
    sent_at: string | null;
    can_resend: boolean;
};

export type AgingBuckets = {
    current: number;
    days_1_30: number;
    days_31_60: number;
    days_61_90: number;
    days_over_90: number;
};

export type PortfolioSummary = {
    borrowers: number;
    loans: number;
    open_loans: number;
    active_loans: number;
    defaulted_loans: number;
    closed_loans: number;
    written_off_loans: number;
    pipeline_loans: number;
    disbursed: number;
    outstanding: number;
    outstanding_principal: number;
    outstanding_interest: number;
    outstanding_fee: number;
    outstanding_penalty: number;
    arrears: number;
    collected: number;
    collected_principal: number;
    collected_interest: number;
    collected_fee: number;
    collected_penalty: number;
};

export type RiskBucket = {
    days: number;
    amount: number;
    ratio: number;
};

export type PortfolioRisk = {
    outstanding: number;
    par: RiskBucket;
    par30: RiskBucket;
    par60: RiskBucket;
    par90: RiskBucket;
};

export type CollectionInstallment = {
    loan_id: number;
    borrower: string | null;
    sequence: number;
    due_date: string;
    amount: number;
    days_past_due: number;
};

export type Collections = {
    as_of: string;
    due_today: number;
    due_this_week: number;
    overdue: number;
    upcoming: CollectionInstallment[];
    overdue_installments: CollectionInstallment[];
};

export type ReportLoanRow = {
    id: number;
    borrower_id: number;
    borrower: string | null;
    product: string | null;
    principal: number;
    status: LoanStatus;
    status_label: string;
    is_open: boolean;
    disbursed_at: string | null;
    paid: number;
    outstanding: number;
    outstanding_principal: number;
    outstanding_interest: number;
    outstanding_fee: number;
    outstanding_penalty: number;
    arrears: number;
    days_past_due: number;
    aging: AgingBuckets;
};

export type RecentPayment = {
    id: number;
    loan_id: number;
    borrower: string | null;
    amount: number;
    method: PaymentMethod;
    method_label: string;
    reference: string | null;
    payment_date: string;
    recorded_by: string | null;
};

export type UpcomingInstallment = {
    loan_id: number;
    sequence: number;
    due_date: string;
    amount: number;
    days_past_due: number;
};

export type BorrowerSummary = {
    borrower: {
        id: number;
        name: string;
        kyc_status: KycStatus;
        kyc_status_label: string;
    };
    loan_count: number;
    open_loan_count: number;
    outstanding: number;
    arrears: number;
    next_due: UpcomingInstallment | null;
    upcoming: UpcomingInstallment[];
    loans: ReportLoanRow[];
    payments: RecentPayment[];
};

export type ActivityLogChange = {
    key: string;
    old: string | null;
    new: string | null;
};

export type ActivityLogRow = {
    id: number;
    event: string;
    event_label: string;
    description: string;
    user_name: string | null;
    user_email: string | null;
    subject_type: string | null;
    subject_id: number | null;
    ip_address: string | null;
    user_agent: string | null;
    changes: ActivityLogChange[];
    created_at: string | null;
};

export type ActivityLogFilters = {
    event: string | null;
    type: string | null;
    user: number | null;
    from: string | null;
    to: string | null;
};

export type ReportFilters = {
    from: string | null;
    to: string | null;
};

export type BorrowerSearchResult = {
    id: number;
    name: string;
    phone: string | null;
    kyc_status: string;
    url: string;
};

export type LoanSearchResult = {
    id: number;
    borrower: string | null;
    status: string;
    status_label: string;
    principal: number;
    url: string;
};

export type GlobalSearchResults = {
    query: string;
    borrowers: BorrowerSearchResult[];
    loans: LoanSearchResult[];
};

export type AppSettings = {
    organisation_name: string;
    currency_symbol: string;
    default_penalty_rate: string;
    default_grace_period_days: string;
    money_rate_limit: string;
    reports_rate_limit: string;
    reminders_rate_limit: string;
    sms_driver: string;
    sms_from: string;
    sms_http_url: string;
    sms_http_method: string;
    sms_http_format: string;
    sms_http_token_configured: boolean;
    sms_http_token_header: string;
    sms_http_token_prefix: string;
    sms_http_headers: string;
    sms_http_to_field: string;
    sms_http_message_field: string;
    sms_http_from_field: string;
    sms_http_timeout: string;
    sms_http_connect_timeout: string;
};

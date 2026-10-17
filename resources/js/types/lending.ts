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
    created_at: string | null;
};

export type BorrowerListItem = Borrower & {
    can: {
        update: boolean;
        delete: boolean;
    };
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
    is_active: boolean;
    created_at: string | null;
};

export type LoanProductListItem = LoanProduct & {
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
    | 'payment_recorded';

export type BorrowerOption = SelectOption<number>;

export type LoanProductOption = SelectOption<number> & {
    interest_rate: string;
    interest_method_label: string;
    repayment_frequency_label: string;
    min_amount: string;
    max_amount: string;
    min_term_months: number;
    max_term_months: number;
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
    status: LoanStatus;
    status_label: string;
    applied_at: string | null;
    approved_at: string | null;
    rejected_at: string | null;
    disbursed_at: string | null;
    notes: string | null;
    created_at: string | null;
};

export type LoanListItem = Loan & {
    can: {
        view: boolean;
        delete: boolean;
    };
};

export type LoanAbilities = {
    approve: boolean;
    reject: boolean;
    disburse: boolean;
    pay: boolean;
    delete: boolean;
};

export type LoanInstallment = {
    sequence: number;
    due_date: string;
    principal_due: string;
    interest_due: string;
    fee_due: string;
    balance: string;
    paid: string;
    outstanding: string;
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
    allocations: PaymentAllocation[];
};

export type PaymentTotals = {
    payable: string;
    paid: string;
    outstanding: string;
    arrears: string;
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

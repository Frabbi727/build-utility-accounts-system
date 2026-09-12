import 'package:equatable/equatable.dart';

/// Single item line in a service charge bill
class BillItemModel extends Equatable {
  final int id;
  final String chargeHeadName;
  final String? chargeHeadCode;
  final String? calculationType;
  final String amount;
  final String? description;
  final String? quantity;
  final String? unitRate;
  final String? unitLabel;

  const BillItemModel({
    required this.id,
    required this.chargeHeadName,
    this.chargeHeadCode,
    this.calculationType,
    required this.amount,
    this.description,
    this.quantity,
    this.unitRate,
    this.unitLabel,
  });

  factory BillItemModel.fromJson(Map<String, dynamic> json) {
    return BillItemModel(
      id: json['id'] as int,
      chargeHeadName: (json['charge_head_name'] ?? json['description'] ?? 'Charge') as String,
      chargeHeadCode: json['charge_head_code'] as String?,
      calculationType: json['calculation_type'] as String?,
      amount: json['amount']?.toString() ?? '0.00',
      description: json['description'] as String?,
      quantity: json['quantity']?.toString(),
      unitRate: json['unit_rate']?.toString(),
      unitLabel: json['unit_label'] as String?,
    );
  }

  @override
  List<Object?> get props => [
        id,
        chargeHeadName,
        chargeHeadCode,
        calculationType,
        amount,
        description,
        quantity,
        unitRate,
        unitLabel,
      ];
}

/// Service charge bill summary model for listing and detail
class BillModel extends Equatable {
  final int id;
  final String billNo;
  final String billingMonth;
  final String billingMonthFormatted;
  final String status; // 'unpaid' | 'paid' | 'partially_paid' | 'overdue'
  final String totalAmount;
  final String paidAmount;
  final String dueAmount;
  final String? monthCharges;
  final String? totalDue;
  final String? advanceHeld;
  final String? arrears;
  final String? dueDate;
  final bool isOverdue;
  final String? downloadPdfUrl;
  final List<BillItemModel> items;

  const BillModel({
    required this.id,
    required this.billNo,
    required this.billingMonth,
    required this.billingMonthFormatted,
    required this.status,
    required this.totalAmount,
    this.paidAmount = '0.00',
    this.dueAmount = '0.00',
    this.monthCharges,
    this.totalDue,
    this.advanceHeld,
    this.arrears,
    this.dueDate,
    this.isOverdue = false,
    this.downloadPdfUrl,
    this.items = const [],
  });

  factory BillModel.fromJson(Map<String, dynamic> json) {
    var rawItems = json['items'];
    List<BillItemModel> parsedItems = [];
    if (rawItems is List) {
      parsedItems = rawItems
          .map((item) => BillItemModel.fromJson(item as Map<String, dynamic>))
          .toList();
    }

    return BillModel(
      id: json['id'] as int,
      billNo: json['bill_no'] as String? ?? '',
      billingMonth: json['billing_month'] as String? ?? '',
      billingMonthFormatted:
          json['billing_month_formatted'] as String? ?? json['billing_month'] ?? '',
      status: json['status'] as String? ?? 'unpaid',
      totalAmount: json['total_amount']?.toString() ?? '0.00',
      paidAmount: json['paid_amount']?.toString() ?? '0.00',
      dueAmount: json['due_amount']?.toString() ?? '0.00',
      monthCharges: json['month_charges']?.toString(),
      totalDue: json['total_due']?.toString(),
      advanceHeld: json['advance_held']?.toString(),
      arrears: json['arrears']?.toString(),
      dueDate: json['due_date'] as String?,
      isOverdue: json['is_overdue'] as bool? ?? false,
      downloadPdfUrl: json['download_pdf_url'] as String?,
      items: parsedItems,
    );
  }

  bool get isPaid => status == 'paid';
  bool get isUnpaid => status == 'unpaid' || status == 'overdue';

  @override
  List<Object?> get props => [
        id,
        billNo,
        billingMonth,
        billingMonthFormatted,
        status,
        totalAmount,
        paidAmount,
        dueAmount,
        monthCharges,
        totalDue,
        advanceHeld,
        arrears,
        dueDate,
        isOverdue,
        downloadPdfUrl,
        items,
      ];
}

import 'package:equatable/equatable.dart';

/// Single item line in a service charge bill
class BillItemModel extends Equatable {
  final int id;
  final String chargeHeadName;
  final String chargeHeadCode;
  final String calculationType;
  final String amount;
  final String? description;

  const BillItemModel({
    required this.id,
    required this.chargeHeadName,
    required this.chargeHeadCode,
    required this.calculationType,
    required this.amount,
    this.description,
  });

  factory BillItemModel.fromJson(Map<String, dynamic> json) {
    return BillItemModel(
      id: json['id'] as int,
      chargeHeadName: json['charge_head_name'] as String? ?? 'Charge',
      chargeHeadCode: json['charge_head_code'] as String? ?? '',
      calculationType: json['calculation_type'] as String? ?? 'fixed',
      amount: json['amount']?.toString() ?? '0.00',
      description: json['description'] as String?,
    );
  }

  @override
  List<Object?> get props => [id, chargeHeadName, chargeHeadCode, calculationType, amount, description];
}

/// Service charge bill summary model for listing
class BillModel extends Equatable {
  final int id;
  final String billNo;
  final String billingMonth;
  final String billingMonthFormatted;
  final String status; // 'unpaid' | 'paid' | 'partially_paid' | 'overdue'
  final String totalAmount;
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
      billingMonthFormatted: json['billing_month_formatted'] as String? ?? json['billing_month'] ?? '',
      status: json['status'] as String? ?? 'unpaid',
      totalAmount: json['total_amount']?.toString() ?? '0.00',
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
        dueDate,
        isOverdue,
        downloadPdfUrl,
        items,
      ];
}

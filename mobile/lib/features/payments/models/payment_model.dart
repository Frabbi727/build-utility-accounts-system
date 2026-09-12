import 'package:equatable/equatable.dart';

/// Offline payment slip submitted by resident awaiting staff verification
class PaymentSubmissionModel extends Equatable {
  final int id;
  final int flatId;
  final String amount;
  final String paymentMethod;
  final String referenceNumber;
  final String? slipUrl;
  final String status; // 'pending' | 'verified' | 'rejected'
  final String? rejectionReason;
  final String createdAt;
  final String? verifiedAt;

  const PaymentSubmissionModel({
    required this.id,
    required this.flatId,
    required this.amount,
    required this.paymentMethod,
    required this.referenceNumber,
    this.slipUrl,
    required this.status,
    this.rejectionReason,
    required this.createdAt,
    this.verifiedAt,
  });

  factory PaymentSubmissionModel.fromJson(Map<String, dynamic> json) {
    return PaymentSubmissionModel(
      id: json['id'] as int,
      flatId: json['flat_id'] as int? ?? 0,
      amount: json['amount']?.toString() ?? '0.00',
      paymentMethod: json['payment_method'] as String? ?? 'bKash',
      referenceNumber: json['reference_number'] as String? ?? '',
      slipUrl: json['slip_url'] as String?,
      status: json['status'] as String? ?? 'pending',
      rejectionReason: json['rejection_reason'] as String?,
      createdAt: json['created_at'] as String? ?? '',
      verifiedAt: json['verified_at'] as String?,
    );
  }

  bool get isPending => status == 'pending';
  bool get isVerified => status == 'verified';
  bool get isRejected => status == 'rejected';

  @override
  List<Object?> get props => [
        id,
        flatId,
        amount,
        paymentMethod,
        referenceNumber,
        slipUrl,
        status,
        rejectionReason,
        createdAt,
        verifiedAt,
      ];
}

/// Official double-entry posted payment receipt
class PaymentReceiptModel extends Equatable {
  final int id;
  final String paymentNo;
  final int flatId;
  final String amount;
  final String paymentDate;
  final String paymentMethod;
  final String? receiptUrl;
  final String createdAt;

  const PaymentReceiptModel({
    required this.id,
    required this.paymentNo,
    required this.flatId,
    required this.amount,
    required this.paymentDate,
    required this.paymentMethod,
    this.receiptUrl,
    required this.createdAt,
  });

  factory PaymentReceiptModel.fromJson(Map<String, dynamic> json) {
    return PaymentReceiptModel(
      id: json['id'] as int,
      paymentNo: json['payment_no'] as String? ?? '',
      flatId: json['flat_id'] as int? ?? 0,
      amount: json['amount']?.toString() ?? '0.00',
      paymentDate: json['payment_date'] as String? ?? '',
      paymentMethod: json['payment_method'] as String? ?? '',
      receiptUrl: json['receipt_url'] as String?,
      createdAt: json['created_at'] as String? ?? '',
    );
  }

  @override
  List<Object?> get props => [
        id,
        paymentNo,
        flatId,
        amount,
        paymentDate,
        paymentMethod,
        receiptUrl,
        createdAt,
      ];
}

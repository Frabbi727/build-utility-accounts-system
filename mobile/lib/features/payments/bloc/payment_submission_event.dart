import 'dart:io';
import 'package:equatable/equatable.dart';

abstract class PaymentSubmissionEvent extends Equatable {
  const PaymentSubmissionEvent();

  @override
  List<Object?> get props => [];
}

class PaymentSubmissionsFetchRequested extends PaymentSubmissionEvent {
  final int? flatId;

  const PaymentSubmissionsFetchRequested({this.flatId});

  @override
  List<Object?> get props => [flatId];
}

class PaymentSubmitRequested extends PaymentSubmissionEvent {
  final int flatId;
  final String amount;
  final String paymentMethod;
  final String referenceNumber;
  final File? slipFile;
  final String? notes;

  const PaymentSubmitRequested({
    required this.flatId,
    required this.amount,
    required this.paymentMethod,
    required this.referenceNumber,
    this.slipFile,
    this.notes,
  });

  @override
  List<Object?> get props => [flatId, amount, paymentMethod, referenceNumber, slipFile?.path, notes];
}

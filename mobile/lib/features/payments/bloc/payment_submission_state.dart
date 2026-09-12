import 'package:equatable/equatable.dart';
import '../models/payment_model.dart';

abstract class PaymentSubmissionState extends Equatable {
  const PaymentSubmissionState();

  @override
  List<Object?> get props => [];
}

class PaymentSubmissionInitial extends PaymentSubmissionState {}

class PaymentSubmissionLoading extends PaymentSubmissionState {}

class PaymentSubmitting extends PaymentSubmissionState {}

class PaymentSubmissionsLoaded extends PaymentSubmissionState {
  final List<PaymentSubmissionModel> submissions;
  final List<PaymentReceiptModel> receipts;

  const PaymentSubmissionsLoaded({
    this.submissions = const [],
    this.receipts = const [],
  });

  @override
  List<Object?> get props => [submissions, receipts];
}

class PaymentSubmitSuccess extends PaymentSubmissionState {
  final PaymentSubmissionModel submission;

  const PaymentSubmitSuccess(this.submission);

  @override
  List<Object?> get props => [submission];
}

class PaymentSubmissionError extends PaymentSubmissionState {
  final String message;

  const PaymentSubmissionError(this.message);

  @override
  List<Object?> get props => [message];
}

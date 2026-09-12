import 'package:equatable/equatable.dart';
import '../models/bill_model.dart';

abstract class BillsState extends Equatable {
  const BillsState();

  @override
  List<Object?> get props => [];
}

class BillsInitial extends BillsState {}

class BillsLoading extends BillsState {}

class BillsLoaded extends BillsState {
  final List<BillModel> bills;
  final String? selectedStatus;
  final int? selectedYear;

  const BillsLoaded({
    required this.bills,
    this.selectedStatus,
    this.selectedYear,
  });

  @override
  List<Object?> get props => [bills, selectedStatus, selectedYear];
}

class BillDetailLoaded extends BillsState {
  final BillModel bill;

  const BillDetailLoaded(this.bill);

  @override
  List<Object?> get props => [bill];
}

class BillsError extends BillsState {
  final String message;

  const BillsError(this.message);

  @override
  List<Object?> get props => [message];
}

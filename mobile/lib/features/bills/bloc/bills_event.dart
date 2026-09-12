import 'package:equatable/equatable.dart';

abstract class BillsEvent extends Equatable {
  const BillsEvent();

  @override
  List<Object?> get props => [];
}

class BillsFetchRequested extends BillsEvent {
  final int? flatId;
  final String? status; // 'unpaid' | 'paid' | null
  final int? year;

  const BillsFetchRequested({this.flatId, this.status, this.year});

  @override
  List<Object?> get props => [flatId, status, year];
}

class BillDetailRequested extends BillsEvent {
  final int billId;

  const BillDetailRequested(this.billId);

  @override
  List<Object?> get props => [billId];
}

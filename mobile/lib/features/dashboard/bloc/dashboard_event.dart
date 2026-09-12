import 'package:equatable/equatable.dart';

abstract class DashboardEvent extends Equatable {
  const DashboardEvent();

  @override
  List<Object?> get props => [];
}

class DashboardFetchRequested extends DashboardEvent {
  final int? flatId;

  const DashboardFetchRequested({this.flatId});

  @override
  List<Object?> get props => [flatId];
}

class DashboardRefreshRequested extends DashboardEvent {}

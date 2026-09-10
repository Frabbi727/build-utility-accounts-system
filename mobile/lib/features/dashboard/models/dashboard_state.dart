import 'package:equatable/equatable.dart';

/// Flat summary for resident multi-flat switcher
class FlatItem extends Equatable {
  final int id;
  final String number;
  final String role; // 'owner' | 'tenant'
  final String buildingName;
  final String buildingCode;

  const FlatItem({
    required this.id,
    required this.number,
    required this.role,
    required this.buildingName,
    required this.buildingCode,
  });

  factory FlatItem.fromJson(Map<String, dynamic> json) {
    final building = json['building'] as Map<String, dynamic>? ?? {};
    return FlatItem(
      id: json['id'] as int,
      number: json['number'] as String? ?? '',
      role: json['role'] as String? ?? 'owner',
      buildingName: (building['name'] ?? json['building_name'] ?? '') as String,
      buildingCode: (building['code'] ?? json['building_code'] ?? '') as String,
    );
  }

  @override
  List<Object?> get props => [id, number, role, buildingName, buildingCode];
}

/// Ledger-derived live balances (zero cached database balance columns)
class ResidentBalances extends Equatable {
  final String totalDue;
  final String advanceHeld;
  final String currentMonthCharges;
  final String arrears;
  final String currency;

  const ResidentBalances({
    required this.totalDue,
    required this.advanceHeld,
    required this.currentMonthCharges,
    required this.arrears,
    this.currency = 'BDT',
  });

  factory ResidentBalances.fromJson(Map<String, dynamic> json) {
    return ResidentBalances(
      totalDue: json['total_due']?.toString() ?? '0.00',
      advanceHeld: json['advance_held']?.toString() ?? '0.00',
      currentMonthCharges: json['current_month_charges']?.toString() ?? '0.00',
      arrears: json['arrears']?.toString() ?? '0.00',
      currency: json['currency']?.toString() ?? 'BDT',
    );
  }

  double get totalDueDouble => double.tryParse(totalDue) ?? 0.0;
  double get advanceHeldDouble => double.tryParse(advanceHeld) ?? 0.0;
  bool get hasOutstandingDue => totalDueDouble > 0.0;

  @override
  List<Object?> get props => [totalDue, advanceHeld, currentMonthCharges, arrears, currency];
}

/// Aggregated dashboard payload for a specific flat
class DashboardData extends Equatable {
  final FlatItem flat;
  final ResidentBalances balances;
  final Map<String, dynamic>? latestBill;
  final List<dynamic> activeNotices;
  final List<dynamic> recentPayments;
  final List<dynamic> myTickets;

  const DashboardData({
    required this.flat,
    required this.balances,
    this.latestBill,
    this.activeNotices = const [],
    this.recentPayments = const [],
    this.myTickets = const [],
  });

  factory DashboardData.fromJson(Map<String, dynamic> json) {
    final flatMap = json['flat'] as Map<String, dynamic>? ?? {};
    final balancesMap = json['balances'] as Map<String, dynamic>? ?? {};

    return DashboardData(
      flat: FlatItem(
        id: flatMap['id'] as int? ?? 0,
        number: flatMap['number'] as String? ?? '',
        role: flatMap['role'] as String? ?? 'resident',
        buildingName: flatMap['building_name'] as String? ?? '',
        buildingCode: flatMap['building_code'] as String? ?? '',
      ),
      balances: ResidentBalances.fromJson(balancesMap),
      latestBill: json['latest_bill'] as Map<String, dynamic>?,
      activeNotices: json['active_notices'] as List<dynamic>? ?? [],
      recentPayments: json['recent_payments'] as List<dynamic>? ?? [],
      myTickets: json['my_tickets'] as List<dynamic>? ?? [],
    );
  }

  @override
  List<Object?> get props => [flat, balances, latestBill, activeNotices, recentPayments, myTickets];
}

/// BLoC State for Dashboard
abstract class DashboardState extends Equatable {
  const DashboardState();
  @override
  List<Object?> get props => [];
}

class DashboardInitial extends DashboardState {}

class DashboardLoading extends DashboardState {}

class DashboardLoaded extends DashboardState {
  final List<FlatItem> userFlats;
  final FlatItem selectedFlat;
  final DashboardData dashboardData;

  const DashboardLoaded({
    required this.userFlats,
    required this.selectedFlat,
    required this.dashboardData,
  });

  @override
  List<Object?> get props => [userFlats, selectedFlat, dashboardData];
}

class DashboardError extends DashboardState {
  final String message;
  const DashboardError(this.message);

  @override
  List<Object?> get props => [message];
}

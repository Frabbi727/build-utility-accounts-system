import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../core/constants/api_endpoints.dart';
import '../../../core/network/api_client.dart';
import '../../../core/storage/secure_storage_service.dart';
import '../models/dashboard_state.dart';
import 'dashboard_event.dart';

class DashboardBloc extends Bloc<DashboardEvent, DashboardState> {
  final ApiClient _apiClient;
  final SecureStorageService _storageService;

  DashboardBloc({
    required ApiClient apiClient,
    required SecureStorageService storageService,
  })  : _apiClient = apiClient,
        _storageService = storageService,
        super(DashboardInitial()) {
    on<DashboardFetchRequested>(_onFetchRequested);
    on<DashboardRefreshRequested>(_onRefreshRequested);
  }

  Future<void> _onFetchRequested(
    DashboardFetchRequested event,
    Emitter<DashboardState> emit,
  ) async {
    emit(DashboardLoading());
    await _loadDashboard(event.flatId, emit);
  }

  Future<void> _onRefreshRequested(
    DashboardRefreshRequested event,
    Emitter<DashboardState> emit,
  ) async {
    int? currentFlatId;
    if (state is DashboardLoaded) {
      currentFlatId = (state as DashboardLoaded).selectedFlat.id;
    }
    await _loadDashboard(currentFlatId, emit);
  }

  Future<void> _loadDashboard(int? targetFlatId, Emitter<DashboardState> emit) async {
    try {
      // 1. Fetch resident flats
      final flatsResponse = await _apiClient.get<List<FlatItem>>(
        ApiEndpoints.flats,
        fromJson: (data) {
          final list = data as List<dynamic>;
          return list.map((item) => FlatItem.fromJson(item as Map<String, dynamic>)).toList();
        },
      );

      if (!flatsResponse.success || flatsResponse.data == null || flatsResponse.data!.isEmpty) {
        emit(const DashboardError('No flats assigned to your account.'));
        return;
      }

      final flats = flatsResponse.data!;
      final savedFlatId = targetFlatId ?? _storageService.getSelectedFlatId();

      final selectedFlat = flats.firstWhere(
        (f) => f.id == savedFlatId,
        orElse: () => flats.first,
      );

      await _storageService.saveSelectedFlatId(selectedFlat.id);

      // 2. Fetch dashboard payload for selected flat
      final dashboardResponse = await _apiClient.get<DashboardData>(
        ApiEndpoints.dashboard,
        queryParameters: {'flat_id': selectedFlat.id},
        fromJson: (data) => DashboardData.fromJson(data as Map<String, dynamic>),
      );

      if (dashboardResponse.success && dashboardResponse.data != null) {
        emit(DashboardLoaded(
          userFlats: flats,
          selectedFlat: selectedFlat,
          dashboardData: dashboardResponse.data!,
        ));
      } else {
        emit(DashboardError(dashboardResponse.message));
      }
    } on ApiException catch (e) {
      emit(DashboardError(e.message));
    } catch (e) {
      emit(DashboardError('Failed to load resident dashboard.'));
    }
  }
}

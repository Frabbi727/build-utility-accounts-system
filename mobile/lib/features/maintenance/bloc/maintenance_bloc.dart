import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../core/constants/api_endpoints.dart';
import '../../../core/network/api_client.dart';
import '../../../core/storage/secure_storage_service.dart';
import '../models/ticket_model.dart';
import 'maintenance_event.dart';
import 'maintenance_state.dart';

class MaintenanceBloc extends Bloc<MaintenanceEvent, MaintenanceState> {
  final ApiClient _apiClient;
  final SecureStorageService _storageService;

  MaintenanceBloc({
    required ApiClient apiClient,
    required SecureStorageService storageService,
  })  : _apiClient = apiClient,
        _storageService = storageService,
        super(MaintenanceInitial()) {
    on<MaintenanceFetchRequested>(_onFetchRequested);
    on<MaintenanceCreateRequested>(_onCreateRequested);
  }

  Future<void> _onFetchRequested(
    MaintenanceFetchRequested event,
    Emitter<MaintenanceState> emit,
  ) async {
    emit(MaintenanceLoading());
    try {
      final flatId = event.flatId ?? _storageService.getSelectedFlatId();
      final queryParams = <String, dynamic>{};
      if (flatId != null) queryParams['flat_id'] = flatId;

      final response = await _apiClient.get<List<MaintenanceTicketModel>>(
        ApiEndpoints.maintenanceRequests,
        queryParameters: queryParams,
        fromJson: (data) {
          final list = data as List<dynamic>;
          return list.map((item) => MaintenanceTicketModel.fromJson(item as Map<String, dynamic>)).toList();
        },
      );

      if (response.success && response.data != null) {
        emit(MaintenanceLoaded(response.data!));
      } else {
        emit(MaintenanceError(response.message));
      }
    } on ApiException catch (e) {
      emit(MaintenanceError(e.message));
    } catch (e) {
      emit(const MaintenanceError('Failed to load maintenance requests.'));
    }
  }

  Future<void> _onCreateRequested(
    MaintenanceCreateRequested event,
    Emitter<MaintenanceState> emit,
  ) async {
    emit(MaintenanceSubmitting());
    try {
      final response = await _apiClient.post<MaintenanceTicketModel>(
        ApiEndpoints.maintenanceRequests,
        data: {
          'flat_id': event.flatId,
          'category': event.category,
          'title': event.title,
          'description': event.description,
          'priority': event.priority,
        },
        fromJson: (data) => MaintenanceTicketModel.fromJson(data as Map<String, dynamic>),
      );

      if (response.success && response.data != null) {
        emit(MaintenanceCreateSuccess(response.data!));
      } else {
        emit(MaintenanceError(response.message));
      }
    } on ApiException catch (e) {
      emit(MaintenanceError(e.message));
    } catch (e) {
      emit(const MaintenanceError('Failed to submit maintenance request.'));
    }
  }
}

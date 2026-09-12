import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../core/constants/api_endpoints.dart';
import '../../../core/network/api_client.dart';
import '../../../core/storage/secure_storage_service.dart';
import '../models/bill_model.dart';
import 'bills_event.dart';
import 'bills_state.dart';

class BillsBloc extends Bloc<BillsEvent, BillsState> {
  final ApiClient _apiClient;
  final SecureStorageService _storageService;

  BillsBloc({
    required ApiClient apiClient,
    required SecureStorageService storageService,
  })  : _apiClient = apiClient,
        _storageService = storageService,
        super(BillsInitial()) {
    on<BillsFetchRequested>(_onFetchRequested);
    on<BillDetailRequested>(_onDetailRequested);
  }

  Future<void> _onFetchRequested(
    BillsFetchRequested event,
    Emitter<BillsState> emit,
  ) async {
    emit(BillsLoading());
    try {
      final flatId = event.flatId ?? _storageService.getSelectedFlatId();
      final queryParams = <String, dynamic>{};
      if (flatId != null) queryParams['flat_id'] = flatId;
      if (event.status != null && event.status!.isNotEmpty) queryParams['status'] = event.status;
      if (event.year != null) queryParams['year'] = event.year;

      final response = await _apiClient.get<List<BillModel>>(
        ApiEndpoints.bills,
        queryParameters: queryParams,
        fromJson: (data) {
          final list = data as List<dynamic>;
          return list.map((item) => BillModel.fromJson(item as Map<String, dynamic>)).toList();
        },
      );

      if (response.success && response.data != null) {
        emit(BillsLoaded(
          bills: response.data!,
          selectedStatus: event.status,
          selectedYear: event.year,
        ));
      } else {
        emit(BillsError(response.message));
      }
    } on ApiException catch (e) {
      emit(BillsError(e.message));
    } catch (e) {
      emit(const BillsError('Failed to load bills.'));
    }
  }

  Future<void> _onDetailRequested(
    BillDetailRequested event,
    Emitter<BillsState> emit,
  ) async {
    emit(BillsLoading());
    try {
      final response = await _apiClient.get<BillModel>(
        ApiEndpoints.billDetail(event.billId),
        fromJson: (data) => BillModel.fromJson(data as Map<String, dynamic>),
      );

      if (response.success && response.data != null) {
        emit(BillDetailLoaded(response.data!));
      } else {
        emit(BillsError(response.message));
      }
    } on ApiException catch (e) {
      emit(BillsError(e.message));
    } catch (e) {
      emit(const BillsError('Failed to load bill details.'));
    }
  }
}

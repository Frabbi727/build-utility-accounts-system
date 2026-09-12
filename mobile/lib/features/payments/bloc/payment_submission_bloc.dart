import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../core/constants/api_endpoints.dart';
import '../../../core/network/api_client.dart';
import '../../../core/storage/secure_storage_service.dart';
import '../models/payment_model.dart';
import 'payment_submission_event.dart';
import 'payment_submission_state.dart';

class PaymentSubmissionBloc extends Bloc<PaymentSubmissionEvent, PaymentSubmissionState> {
  final ApiClient _apiClient;
  final SecureStorageService _storageService;

  PaymentSubmissionBloc({
    required ApiClient apiClient,
    required SecureStorageService storageService,
  })  : _apiClient = apiClient,
        _storageService = storageService,
        super(PaymentSubmissionInitial()) {
    on<PaymentSubmissionsFetchRequested>(_onFetchRequested);
    on<PaymentSubmitRequested>(_onSubmitRequested);
  }

  Future<void> _onFetchRequested(
    PaymentSubmissionsFetchRequested event,
    Emitter<PaymentSubmissionState> emit,
  ) async {
    emit(PaymentSubmissionLoading());
    try {
      final flatId = event.flatId ?? _storageService.getSelectedFlatId();
      final queryParams = <String, dynamic>{};
      if (flatId != null) queryParams['flat_id'] = flatId;

      // 1. Fetch verification queue
      final subResponse = await _apiClient.get<List<PaymentSubmissionModel>>(
        ApiEndpoints.paymentSubmissions,
        queryParameters: queryParams,
        fromJson: (data) {
          final list = data as List<dynamic>;
          return list.map((item) => PaymentSubmissionModel.fromJson(item as Map<String, dynamic>)).toList();
        },
      );

      // 2. Fetch posted receipts
      final recResponse = await _apiClient.get<List<PaymentReceiptModel>>(
        ApiEndpoints.payments,
        queryParameters: queryParams,
        fromJson: (data) {
          final list = data as List<dynamic>;
          return list.map((item) => PaymentReceiptModel.fromJson(item as Map<String, dynamic>)).toList();
        },
      );

      emit(PaymentSubmissionsLoaded(
        submissions: subResponse.data ?? [],
        receipts: recResponse.data ?? [],
      ));
    } on ApiException catch (e) {
      emit(PaymentSubmissionError(e.message));
    } catch (e) {
      emit(const PaymentSubmissionError('Failed to load payments history.'));
    }
  }

  Future<void> _onSubmitRequested(
    PaymentSubmitRequested event,
    Emitter<PaymentSubmissionState> emit,
  ) async {
    emit(PaymentSubmitting());
    try {
      final fields = <String, dynamic>{
        'flat_id': event.flatId,
        'amount': event.amount,
        'payment_method': event.paymentMethod,
        'reference_number': event.referenceNumber,
        if (event.notes != null && event.notes!.isNotEmpty) 'notes': event.notes,
      };

      final response = event.slipFile != null
          ? await _apiClient.uploadMultipart<PaymentSubmissionModel>(
              ApiEndpoints.paymentSubmissions,
              fields: fields,
              fileField: 'slip',
              file: event.slipFile!,
              fromJson: (data) => PaymentSubmissionModel.fromJson(data as Map<String, dynamic>),
            )
          : await _apiClient.post<PaymentSubmissionModel>(
              ApiEndpoints.paymentSubmissions,
              data: fields,
              fromJson: (data) => PaymentSubmissionModel.fromJson(data as Map<String, dynamic>),
            );

      if (response.success && response.data != null) {
        emit(PaymentSubmitSuccess(response.data!));
      } else {
        emit(PaymentSubmissionError(response.message));
      }
    } on ApiException catch (e) {
      emit(PaymentSubmissionError(e.message));
    } catch (e) {
      emit(const PaymentSubmissionError('Failed to submit payment. Please try again.'));
    }
  }
}

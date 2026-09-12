import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../core/constants/api_endpoints.dart';
import '../../../core/network/api_client.dart';
import '../models/notice_model.dart';
import 'notice_event.dart';
import 'notice_state.dart';

class NoticeBloc extends Bloc<NoticeEvent, NoticeState> {
  final ApiClient _apiClient;

  NoticeBloc({
    required ApiClient apiClient,
  })  : _apiClient = apiClient,
        super(NoticeInitial()) {
    on<NoticesFetchRequested>(_onFetchRequested);
  }

  Future<void> _onFetchRequested(
    NoticesFetchRequested event,
    Emitter<NoticeState> emit,
  ) async {
    emit(NoticeLoading());
    try {
      final response = await _apiClient.get<List<NoticeModel>>(
        ApiEndpoints.notices,
        fromJson: (data) {
          final list = data as List<dynamic>;
          return list.map((item) => NoticeModel.fromJson(item as Map<String, dynamic>)).toList();
        },
      );

      if (response.success && response.data != null) {
        emit(NoticeLoaded(response.data!));
      } else {
        emit(NoticeError(response.message));
      }
    } on ApiException catch (e) {
      emit(NoticeError(e.message));
    } catch (e) {
      emit(const NoticeError('Failed to load notices.'));
    }
  }
}

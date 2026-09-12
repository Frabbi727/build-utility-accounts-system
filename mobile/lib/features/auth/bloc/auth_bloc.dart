import 'package:flutter_bloc/flutter_bloc.dart';
import '../../../core/constants/api_endpoints.dart';
import '../../../core/network/api_client.dart';
import '../../../core/storage/secure_storage_service.dart';
import '../../dashboard/models/dashboard_state.dart';
import '../models/user_model.dart';
import 'auth_event.dart';
import 'auth_state.dart';

class AuthBloc extends Bloc<AuthEvent, AuthState> {
  final ApiClient _apiClient;
  final SecureStorageService _storageService;

  AuthBloc({
    required ApiClient apiClient,
    required SecureStorageService storageService,
  })  : _apiClient = apiClient,
        _storageService = storageService,
        super(AuthInitial()) {
    on<AuthCheckRequested>(_onCheckRequested);
    on<AuthLoginRequested>(_onLoginRequested);
    on<AuthLogoutRequested>(_onLogoutRequested);
    on<AuthSelectFlatRequested>(_onSelectFlatRequested);

    _apiClient.onSessionExpired = () {
      add(AuthLogoutRequested());
    };
  }

  Future<void> _onCheckRequested(
    AuthCheckRequested event,
    Emitter<AuthState> emit,
  ) async {
    emit(AuthLoading());
    try {
      final token = await _storageService.getToken();
      if (token == null || token.isEmpty) {
        emit(const AuthUnauthenticated());
        return;
      }

      final response = await _apiClient.get(
        ApiEndpoints.me,
        fromJson: (data) => UserModel.fromJson(data as Map<String, dynamic>),
      );

      if (response.success && response.data != null) {
        final user = response.data!;
        FlatItem? selectedFlat;
        final savedFlatId = _storageService.getSelectedFlatId();
        if (user.flats.isNotEmpty) {
          selectedFlat = user.flats.firstWhere(
            (f) => f.id == savedFlatId,
            orElse: () => user.flats.first,
          );
        }

        emit(AuthAuthenticated(user: user, selectedFlat: selectedFlat));
      } else {
        await _storageService.deleteToken();
        emit(AuthUnauthenticated(response.message));
      }
    } catch (e) {
      await _storageService.deleteToken();
      emit(const AuthUnauthenticated());
    }
  }

  Future<void> _onLoginRequested(
    AuthLoginRequested event,
    Emitter<AuthState> emit,
  ) async {
    emit(AuthLoading());
    try {
      final response = await _apiClient.post(
        ApiEndpoints.login,
        data: {
          'login': event.login.trim(),
          'password': event.password,
        },
        fromJson: (data) => data as Map<String, dynamic>,
      );

      if (response.success && response.data != null) {
        final data = response.data!;
        final token = data['token'] as String;
        final userMap = data['user'] as Map<String, dynamic>;
        final user = UserModel.fromJson(userMap);

        await _apiClient.saveToken(token);
        await _storageService.saveToken(token);

        FlatItem? selectedFlat = user.flats.isNotEmpty ? user.flats.first : null;
        if (selectedFlat != null) {
          await _storageService.saveSelectedFlatId(selectedFlat.id);
        }

        emit(AuthAuthenticated(user: user, selectedFlat: selectedFlat));
      } else {
        emit(AuthError(response.message));
      }
    } on ApiException catch (e) {
      emit(AuthError(e.message));
    } catch (e) {
      emit(AuthError('Login failed. Please check your connection and credentials.'));
    }
  }

  Future<void> _onLogoutRequested(
    AuthLogoutRequested event,
    Emitter<AuthState> emit,
  ) async {
    try {
      await _apiClient.post(ApiEndpoints.logout);
    } catch (_) {}

    await _apiClient.clearToken();
    await _storageService.clearAll();
    emit(const AuthUnauthenticated());
  }

  Future<void> _onSelectFlatRequested(
    AuthSelectFlatRequested event,
    Emitter<AuthState> emit,
  ) async {
    if (state is AuthAuthenticated) {
      final current = state as AuthAuthenticated;
      final found = current.user.flats.firstWhere(
        (f) => f.id == event.flatId,
        orElse: () => current.user.flats.first,
      );
      await _storageService.saveSelectedFlatId(found.id);
      emit(current.copyWith(selectedFlat: found));
    }
  }
}

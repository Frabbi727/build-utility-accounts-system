import 'dart:io';
import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import '../models/api_response.dart';

/// Custom exception for API errors containing backend response envelope
class ApiException implements Exception {
  final int? statusCode;
  final String message;
  final Map<String, List<String>>? errors;

  ApiException({
    this.statusCode,
    required this.message,
    this.errors,
  });

  @override
  String toString() => 'ApiException [$statusCode]: $message';
}

/// Unified API Client wrapping Dio with secure Sanctum token management
class ApiClient {
  final Dio _dio;
  final FlutterSecureStorage _secureStorage;
  static const String _tokenKey = 'resident_auth_token';

  // Stream/callback for authentication expiration (401 Unauthorized)
  void Function()? onSessionExpired;

  ApiClient({
    required String baseUrl,
    Dio? dio,
    FlutterSecureStorage? secureStorage,
    this.onSessionExpired,
  })  : _secureStorage = secureStorage ?? const FlutterSecureStorage(),
        _dio = dio ??
            Dio(
              BaseOptions(
                baseUrl: baseUrl,
                connectTimeout: const Duration(seconds: 15),
                receiveTimeout: const Duration(seconds: 15),
                headers: {
                  'Accept': 'application/json',
                  'Content-Type': 'application/json',
                },
              ),
            ) {
    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          final token = await _secureStorage.read(key: _tokenKey);
          if (token != null && token.isNotEmpty) {
            options.headers['Authorization'] = 'Bearer $token';
          }
          return handler.next(options);
        },
        onError: (DioException error, handler) async {
          if (error.response?.statusCode == 401) {
            await clearToken();
            onSessionExpired?.call();
          }
          return handler.next(error);
        },
      ),
    );
  }

  /// Store Sanctum plainTextToken
  Future<void> saveToken(String token) async {
    await _secureStorage.write(key: _tokenKey, value: token);
  }

  /// Retrieve current Sanctum token
  Future<String?> getToken() async {
    return await _secureStorage.read(key: _tokenKey);
  }

  /// Remove token upon logout
  Future<void> clearToken() async {
    await _secureStorage.delete(key: _tokenKey);
  }

  /// GET Request with unified response parsing
  Future<ApiResponse<T>> get<T>(
    String path, {
    Map<String, dynamic>? queryParameters,
    T Function(dynamic data)? fromJson,
  }) async {
    try {
      final response = await _dio.get(path, queryParameters: queryParameters);
      return ApiResponse<T>.fromJson(response.data as Map<String, dynamic>, fromJson);
    } on DioException catch (e) {
      throw _handleDioError(e);
    }
  }

  /// POST Request with JSON body
  Future<ApiResponse<T>> post<T>(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    T Function(dynamic data)? fromJson,
  }) async {
    try {
      final response = await _dio.post(path, data: data, queryParameters: queryParameters);
      return ApiResponse<T>.fromJson(response.data as Map<String, dynamic>, fromJson);
    } on DioException catch (e) {
      throw _handleDioError(e);
    }
  }

  /// Multipart POST Request for offline payment receipts or ticket photos
  Future<ApiResponse<T>> uploadMultipart<T>(
    String path, {
    required Map<String, dynamic> fields,
    required String fileField,
    required File file,
    T Function(dynamic data)? fromJson,
  }) async {
    try {
      final fileName = file.path.split('/').last;
      final formDataMap = Map<String, dynamic>.from(fields);
      formDataMap[fileField] = await MultipartFile.fromFile(
        file.path,
        filename: fileName,
      );

      final formData = FormData.fromMap(formDataMap);

      final response = await _dio.post(
        path,
        data: formData,
        options: Options(
          contentType: 'multipart/form-data',
        ),
      );

      return ApiResponse<T>.fromJson(response.data as Map<String, dynamic>, fromJson);
    } on DioException catch (e) {
      throw _handleDioError(e);
    }
  }

  /// Handle Dio error and unwrap Laravel validation / business errors
  ApiException _handleDioError(DioException e) {
    final response = e.response;
    if (response != null && response.data is Map<String, dynamic>) {
      final data = response.data as Map<String, dynamic>;
      final parsed = ApiResponse.fromJson(data, null);
      return ApiException(
        statusCode: response.statusCode,
        message: parsed.firstErrorMessage ?? parsed.message,
        errors: parsed.errors,
      );
    }

    return ApiException(
      statusCode: e.response?.statusCode,
      message: e.message ?? 'Network connection error. Please try again.',
    );
  }
}

/// Unified API response models for Resident Mobile App.
/// Maps directly to the Laravel backend envelope:
/// `{ success: bool, message: String, data: T?, meta?: ApiMeta, links?: ApiLinks, errors?: Map }`
class ApiResponse<T> {
  final bool success;
  final String message;
  final T? data;
  final ApiMeta? meta;
  final ApiLinks? links;
  final Map<String, List<String>>? errors;

  const ApiResponse({
    required this.success,
    required this.message,
    this.data,
    this.meta,
    this.links,
    this.errors,
  });

  factory ApiResponse.fromJson(
    Map<String, dynamic> json,
    T Function(dynamic dataJson)? fromJsonT,
  ) {
    Map<String, List<String>>? parsedErrors;
    if (json['errors'] is Map) {
      parsedErrors = {};
      (json['errors'] as Map<String, dynamic>).forEach((key, val) {
        if (val is List) {
          parsedErrors![key] = val.map((e) => e.toString()).toList();
        } else if (val != null) {
          parsedErrors![key] = [val.toString()];
        }
      });
    }

    return ApiResponse<T>(
      success: json['success'] as bool? ?? false,
      message: json['message'] as String? ?? '',
      data: (json['data'] != null && fromJsonT != null)
          ? fromJsonT(json['data'])
          : (json['data'] as T?),
      meta: json['meta'] != null && json['meta'] is Map<String, dynamic>
          ? ApiMeta.fromJson(json['meta'] as Map<String, dynamic>)
          : null,
      links: json['links'] != null && json['links'] is Map<String, dynamic>
          ? ApiLinks.fromJson(json['links'] as Map<String, dynamic>)
          : null,
      errors: parsedErrors,
    );
  }

  /// Get the first validation error message if present
  String? get firstErrorMessage {
    if (errors == null || errors!.isEmpty) return message.isNotEmpty ? message : null;
    return errors!.values.first.firstOrNull ?? message;
  }
}

class ApiMeta {
  final int currentPage;
  final int? lastPage;
  final int perPage;
  final int total;
  final int? from;
  final int? to;

  const ApiMeta({
    required this.currentPage,
    this.lastPage,
    required this.perPage,
    required this.total,
    this.from,
    this.to,
  });

  factory ApiMeta.fromJson(Map<String, dynamic> json) {
    return ApiMeta(
      currentPage: json['current_page'] as int? ?? 1,
      lastPage: json['last_page'] as int?,
      perPage: json['per_page'] as int? ?? 15,
      total: json['total'] as int? ?? 0,
      from: json['from'] as int?,
      to: json['to'] as int?,
    );
  }

  bool get hasNextPage => lastPage != null && currentPage < lastPage!;
}

class ApiLinks {
  final String? first;
  final String? last;
  final String? prev;
  final String? next;

  const ApiLinks({
    this.first,
    this.last,
    this.prev,
    this.next,
  });

  factory ApiLinks.fromJson(Map<String, dynamic> json) {
    return ApiLinks(
      first: json['first'] as String?,
      last: json['last'] as String?,
      prev: json['prev'] as String?,
      next: json['next'] as String?,
    );
  }
}

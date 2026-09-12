/// Centralized API endpoint constants for UAS Resident Mobile App
class ApiEndpoints {
  // Auth
  static const String login = '/auth/login';
  static const String logout = '/auth/logout';
  static const String me = '/auth/me';
  static const String fcmToken = '/auth/fcm-token';
  static const String changePassword = '/auth/change-password';

  // Resident
  static const String flats = '/resident/flats';
  static const String dashboard = '/resident/dashboard';
  static const String bills = '/resident/bills';
  static String billDetail(int id) => '/resident/bills/$id';
  static String billPdf(int id) => '/resident/bills/$id/pdf';
  static const String paymentSubmissions = '/resident/payment-submissions';
  static const String payments = '/resident/payments';
  static String paymentReceipt(int id) => '/resident/payments/$id/receipt';
  static const String maintenanceRequests = '/resident/maintenance-requests';
  static String maintenanceDetail(int id) => '/resident/maintenance-requests/$id';
  static const String notices = '/resident/notices';
  static String noticeDetail(int id) => '/resident/notices/$id';
}

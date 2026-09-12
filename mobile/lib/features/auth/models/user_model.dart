import 'package:equatable/equatable.dart';
import '../../dashboard/models/dashboard_state.dart';

class UserModel extends Equatable {
  final int id;
  final String name;
  final String email;
  final String? phone;
  final String role;
  final List<FlatItem> flats;

  const UserModel({
    required this.id,
    required this.name,
    required this.email,
    this.phone,
    required this.role,
    this.flats = const [],
  });

  factory UserModel.fromJson(Map<String, dynamic> json) {
    var rawFlats = json['flats'];
    List<FlatItem> flatsList = [];
    if (rawFlats is List) {
      flatsList = rawFlats
          .map((f) => FlatItem.fromJson(f as Map<String, dynamic>))
          .toList();
    }

    return UserModel(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      email: json['email'] as String? ?? '',
      phone: json['phone'] as String?,
      role: json['role'] as String? ?? 'resident',
      flats: flatsList,
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'email': email,
        'phone': phone,
        'role': role,
      };

  @override
  List<Object?> get props => [id, name, email, phone, role, flats];
}

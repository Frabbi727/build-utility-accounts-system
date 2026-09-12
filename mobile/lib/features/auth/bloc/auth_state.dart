import 'package:equatable/equatable.dart';
import '../models/user_model.dart';
import '../../dashboard/models/dashboard_state.dart';

abstract class AuthState extends Equatable {
  const AuthState();

  @override
  List<Object?> get props => [];
}

class AuthInitial extends AuthState {}

class AuthLoading extends AuthState {}

class AuthAuthenticated extends AuthState {
  final UserModel user;
  final FlatItem? selectedFlat;

  const AuthAuthenticated({
    required this.user,
    this.selectedFlat,
  });

  AuthAuthenticated copyWith({
    UserModel? user,
    FlatItem? selectedFlat,
  }) {
    return AuthAuthenticated(
      user: user ?? this.user,
      selectedFlat: selectedFlat ?? this.selectedFlat,
    );
  }

  @override
  List<Object?> get props => [user, selectedFlat];
}

class AuthUnauthenticated extends AuthState {
  final String? message;

  const AuthUnauthenticated([this.message]);

  @override
  List<Object?> get props => [message];
}

class AuthError extends AuthState {
  final String message;

  const AuthError(this.message);

  @override
  List<Object?> get props => [message];
}

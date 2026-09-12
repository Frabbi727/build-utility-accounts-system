import 'package:equatable/equatable.dart';

abstract class AuthEvent extends Equatable {
  const AuthEvent();

  @override
  List<Object?> get props => [];
}

class AuthCheckRequested extends AuthEvent {}

class AuthLoginRequested extends AuthEvent {
  final String login; // email or phone
  final String password;

  const AuthLoginRequested({required this.login, required this.password});

  @override
  List<Object?> get props => [login, password];
}

class AuthLogoutRequested extends AuthEvent {}

class AuthSelectFlatRequested extends AuthEvent {
  final int flatId;

  const AuthSelectFlatRequested(this.flatId);

  @override
  List<Object?> get props => [flatId];
}

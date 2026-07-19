// Smoke test: the app boots to a first frame for a signed-out user
// (no token in secure storage), rather than the default counter template.

import 'package:flutter_test/flutter_test.dart';

import 'package:panthers_app/api/prx3_api.dart';
import 'package:panthers_app/main.dart';

void main() {
  testWidgets('App boots and reaches a first frame', (WidgetTester tester) async {
    await tester.pumpWidget(PanthersApp(api: Prx3Api('https://example.test/wp-json/prx3/v1')));
    await tester.pump();
    expect(find.byType(PanthersApp), findsOneWidget);
  });
}

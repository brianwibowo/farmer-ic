import raw from './data/farmDemoData.json';
import { resolveFixtureAssets } from './assets';
// All records and photographs originate in commit 73f2555, never shared DB props.
export default resolveFixtureAssets(raw);

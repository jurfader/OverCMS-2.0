import { PageHeader } from '@/components/layout/Shell';
import { Card } from '@/components/ui/Card';

export function Placeholder({ title, description }: { title: string; description?: string }) {
  return (
    <>
      <PageHeader title={title} description={description} />
      <Card>
        <p className="text-sm text-[var(--color-muted-foreground)]">
          Ta sekcja zostanie dodana w kolejnej wersji OverCMS.
        </p>
      </Card>
    </>
  );
}

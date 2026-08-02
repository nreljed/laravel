import { useCallback, useRef, useState } from "react";
import { useLexicalComposerContext } from "@lexical/react/LexicalComposerContext";
import { INSERT_TABLE_COMMAND } from "@lexical/table";
import { $getSelection, type BaseSelection } from "lexical";
import { Table } from "lucide-react";
import { Button } from "@/Components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/Components/ui/dialog";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/Components/ui/select";

type HeaderOption = "both" | "row" | "column" | "none";

interface InsertTableDialogProps {
  open: boolean;
  onOpenChange: (_open: boolean) => void;
}

export function InsertTableDialog({ open, onOpenChange }: InsertTableDialogProps) {
  const [editor] = useLexicalComposerContext();
  const [rows, setRows] = useState(5);
  const [columns, setColumns] = useState(5);
  const [headers, setHeaders] = useState<HeaderOption>("both");
  const savedSelectionRef = useRef<BaseSelection | null>(null);

  const handleOpenChange = useCallback(
    (isOpen: boolean) => {
      if (isOpen) {
        editor.read(() => {
          const selection = $getSelection();
          if (selection) {
            savedSelectionRef.current = selection.clone();
          }
        });
      }
      onOpenChange(isOpen);
    },
    [editor, onOpenChange],
  );

  const handleInsert = useCallback(() => {
    if (!(rows > 0 && rows <= 500 && columns > 0 && columns <= 50)) {
      return;
    }

    const includeHeaders =
      headers === "both"
        ? true
        : headers === "none"
          ? false
          : { rows: headers === "row", columns: headers === "column" };

    editor.dispatchCommand(INSERT_TABLE_COMMAND, {
      rows: String(rows),
      columns: String(columns),
      includeHeaders,
    });
    onOpenChange(false);
    setRows(5);
    setColumns(5);
    setHeaders("both");
    savedSelectionRef.current = null;
  }, [editor, rows, columns, headers, onOpenChange]);

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key !== "Enter") {
      return;
    }

    e.preventDefault();
    handleInsert();
  };

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Table className="h-5 w-5" />
            Insert Table
          </DialogTitle>
          <DialogDescription>
            Choose the number of rows, columns, and headers for your table.
          </DialogDescription>
        </DialogHeader>
        <div className="grid grid-cols-2 gap-4 py-2">
          <div className="space-y-2">
            <Label htmlFor="table-rows">Rows</Label>
            <Input
              id="table-rows"
              type="number"
              min={1}
              max={500}
              value={rows}
              onChange={(e) => setRows(Number(e.target.value))}
              onKeyDown={handleKeyDown}
            />
          </div>
          <div className="space-y-2">
            <Label htmlFor="table-columns">Columns</Label>
            <Input
              id="table-columns"
              type="number"
              min={1}
              max={50}
              value={columns}
              onChange={(e) => setColumns(Number(e.target.value))}
              onKeyDown={handleKeyDown}
            />
          </div>
        </div>
        <div className="space-y-2 pb-2">
          <Label htmlFor="table-headers">Headers</Label>
          <Select value={headers} onValueChange={(v) => setHeaders(v as HeaderOption)}>
            <SelectTrigger id="table-headers">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="both">Row + Column</SelectItem>
              <SelectItem value="row">First row only</SelectItem>
              <SelectItem value="column">First column only</SelectItem>
              <SelectItem value="none">No headers</SelectItem>
            </SelectContent>
          </Select>
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button
            type="button"
            onClick={handleInsert}
            disabled={rows <= 0 || rows > 500 || columns <= 0 || columns > 50}
          >
            Insert
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
